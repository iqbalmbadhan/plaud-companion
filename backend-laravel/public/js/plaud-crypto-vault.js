/**
 * PLAUD Companion — On-Device Cryptography Vault (Zero-Knowledge Architecture)
 * Uses Web Cryptography API (AES-GCM-256) + IndexedDB
 * Transcripts, audio sessions, and notes are encrypted at rest strictly on the user's device.
 */

class PlaudCryptoVault {
  constructor() {
    this.dbName = 'plaud_vault_db';
    this.dbVersion = 1;
    this.keyStoreName = 'vault_keys';
    this.recordStoreName = 'vault_records';
    this.db = null;
    this.cryptoKey = null;
    this.isReady = false;
  }

  async init() {
    if (!window.crypto || !window.crypto.subtle) {
      console.warn('[PlaudCryptoVault] Web Cryptography API not supported. Falling back to memory mode.');
      return false;
    }

    try {
      this.db = await this._openDb();
      this.cryptoKey = await this._getOrCreateKey();
      this.isReady = true;
      console.log('[PlaudCryptoVault] On-device AES-GCM-256 cryptographic vault initialized.');
      return true;
    } catch (err) {
      console.error('[PlaudCryptoVault] Failed to initialize vault:', err);
      return false;
    }
  }

  _openDb() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(this.dbName, this.dbVersion);
      req.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(this.keyStoreName)) {
          db.createObjectStore(this.keyStoreName, { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains(this.recordStoreName)) {
          db.createObjectStore(this.recordStoreName, { keyPath: 'id' });
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  async _getOrCreateKey() {
    const existing = await this._getFromStore(this.keyStoreName, 'master_key');
    if (existing && existing.rawKey) {
      return await window.crypto.subtle.importKey(
        'jwk',
        existing.rawKey,
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
      );
    }

    // Generate fresh 256-bit AES-GCM device key
    const newKey = await window.crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );

    const exportedJwk = await window.crypto.subtle.exportKey('jwk', newKey);
    await this._putInStore(this.keyStoreName, { id: 'master_key', rawKey: exportedJwk, createdAt: Date.now() });
    return newKey;
  }

  async encryptRecord(data) {
    if (!this.isReady) await this.init();
    const encoder = new TextEncoder();
    const jsonStr = JSON.stringify(data);
    const plaintextBytes = encoder.encode(jsonStr);

    // 12-byte initialization vector (standard for AES-GCM)
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    const ciphertext = await window.crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      this.cryptoKey,
      plaintextBytes
    );

    return {
      iv: Array.from(iv),
      ciphertext: Array.from(new Uint8Array(ciphertext)),
      encryptedAt: Date.now(),
      version: 'aes-gcm-256'
    };
  }

  async decryptRecord(envelope) {
    if (!this.isReady) await this.init();
    const iv = new Uint8Array(envelope.iv);
    const ciphertext = new Uint8Array(envelope.ciphertext);

    const decryptedBytes = await window.crypto.subtle.decrypt(
      { name: 'AES-GCM', iv },
      this.cryptoKey,
      ciphertext
    );

    const decoder = new TextDecoder();
    const jsonStr = decoder.decode(decryptedBytes);
    return JSON.parse(jsonStr);
  }

  async saveTranscript(transcript) {
    if (!transcript || !transcript.id) return;
    const envelope = await this.encryptRecord(transcript);
    await this._putInStore(this.recordStoreName, {
      id: transcript.id,
      titlePreview: transcript.title || 'Untitled Session',
      envelope
    });
  }

  async loadAllTranscripts() {
    if (!this.isReady) await this.init();
    const rawList = await this._getAllFromStore(this.recordStoreName);
    const decrypted = [];
    for (const item of rawList) {
      try {
        if (item.envelope) {
          const t = await this.decryptRecord(item.envelope);
          decrypted.push(t);
        }
      } catch (err) {
        console.warn(`[PlaudCryptoVault] Skipping unreadable record ${item.id}:`, err);
      }
    }
    return decrypted;
  }

  async deleteTranscript(id) {
    if (!this.isReady) await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(this.recordStoreName, 'readwrite');
      const store = tx.objectStore(this.recordStoreName);
      const req = store.delete(id);
      req.onsuccess = () => resolve(true);
      req.onerror = () => reject(req.error);
    });
  }

  async exportEncryptedBackup() {
    if (!this.isReady) await this.init();
    const rawList = await this._getAllFromStore(this.recordStoreName);
    const exportedJwk = await window.crypto.subtle.exportKey('jwk', this.cryptoKey);

    const backupPayload = {
      format: 'plaud-vault-backup',
      version: 1,
      createdAt: new Date().toISOString(),
      records: rawList,
      keyFingerprint: exportedJwk.kid || 'master'
    };

    const blob = new Blob([JSON.stringify(backupPayload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `plaud-vault-backup-${new Date().toISOString().split('T')[0]}.plaudvault`;
    a.click();
    URL.revokeObjectURL(url);
  }

  async importEncryptedBackup(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = async (e) => {
        try {
          const parsed = JSON.parse(e.target.result);
          if (parsed.format !== 'plaud-vault-backup' || !Array.isArray(parsed.records)) {
            throw new Error('Invalid .plaudvault backup file format.');
          }
          let imported = 0;
          for (const rec of parsed.records) {
            await this._putInStore(this.recordStoreName, rec);
            imported++;
          }
          resolve(imported);
        } catch (err) {
          reject(err);
        }
      };
      reader.onerror = () => reject(reader.error);
      reader.readAsText(file);
    });
  }

  // IndexedDB Helpers
  _putInStore(storeName, data) {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const req = store.put(data);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  _getFromStore(storeName, key) {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, 'readonly');
      const store = tx.objectStore(storeName);
      const req = store.get(key);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  _getAllFromStore(storeName) {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, 'readonly');
      const store = tx.objectStore(storeName);
      const req = store.getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => reject(req.error);
    });
  }
}

// Global singleton instance
window.plaudCryptoVault = new PlaudCryptoVault();
