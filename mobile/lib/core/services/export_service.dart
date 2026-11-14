import 'dart:convert';
import 'dart:typed_data';
import '../models/app_models.dart';

class ExportService {
  /// Builds a valid PDF-1.4 file from a transcript
  static Uint8List buildPdf({
    required Transcript transcript,
    String organization = 'PLAUD Companion Mobile',
    bool includeSummary = true,
    bool includeActions = true,
  }) {
    final lines = <String>[];
    lines.add(organization.toUpperCase());
    lines.add('DOCUMENT: ${transcript.title.toUpperCase()}');
    lines.add('SOURCE: ${transcript.sourceFormat} | RECORDED: ${transcript.recordedAt.toIso8601String().substring(0, 10)}');
    lines.add('================================================================================');
    lines.add('');

    if (includeSummary && transcript.aiSummary != null && transcript.aiSummary!.isNotEmpty) {
      lines.add('[1. EXECUTIVE SUMMARY & KEY TAKEAWAYS]');
      for (final sLine in transcript.aiSummary!.split('\n')) {
        if (sLine.trim().isNotEmpty) {
          lines.add('- ${sLine.trim()}');
        }
      }
      lines.add('');
    }

    if (includeActions && transcript.actionItems.isNotEmpty) {
      lines.add('[2. GROUNDED ACTION ITEMS]');
      for (final a in transcript.actionItems) {
        final check = a.isDone ? '[X]' : '[ ]';
        final meta = [
          if (a.assignee != null) 'Owner: ${a.assignee}',
          if (a.dueDate != null) 'Due: ${a.dueDate}',
        ].join(' | ');
        lines.add('$check ${a.task} ${meta.isNotEmpty ? '($meta)' : ''}');
      }
      lines.add('');
    }

    lines.add('[3. VERBATIM TRANSCRIPT WITH SPEAKER DIARIZATION]');
    if (transcript.segments.isNotEmpty) {
      for (final seg in transcript.segments) {
        lines.add('[${seg.timeFormatted}] ${seg.speaker}:');
        lines.add('   ${seg.text}');
        lines.add('');
      }
    } else {
      for (final raw in transcript.contentText.split('\n')) {
        if (raw.trim().isNotEmpty) lines.add(raw.trim());
      }
    }

    final streamOps = <String>['BT', '/F1 10 Tf', '50 750 Td', '13 TL'];
    for (int i = 0; i < lines.length; i++) {
      final safeText = lines[i].replaceAll(RegExp(r'[^\x20-\x7E]'), '?');
      final escaped = safeText.replaceAll('\\', '\\\\').replaceAll('(', '\\(').replaceAll(')', '\\)');
      if (i == 0) {
        streamOps.add('($escaped) Tj');
      } else {
        streamOps.add('T* ($escaped) Tj');
      }
    }
    streamOps.add('ET');
    final streamContent = streamOps.join('\n');
    final streamBytes = utf8.encode(streamContent);

    final objects = <int, String>{
      1: '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n',
      2: '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n',
      3: '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n',
      4: '4 0 obj\n<< /Length ${streamBytes.length} >>\nstream\n$streamContent\nendstream\nendobj\n',
      5: '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n',
    };

    final buffer = BytesBuilder();
    buffer.add(utf8.encode('%PDF-1.4\n'));
    final offsets = <int, int>{};
    int currentOffset = buffer.length;

    for (int i = 1; i <= 5; i++) {
      offsets[i] = currentOffset;
      final bytes = utf8.encode(objects[i]!);
      buffer.add(bytes);
      currentOffset += bytes.length;
    }

    final xrefOffset = currentOffset;
    buffer.add(utf8.encode('xref\n0 6\n'));
    buffer.add(utf8.encode('0000000000 65535 f \n'));
    for (int i = 1; i <= 5; i++) {
      final offStr = offsets[i]!.toString().padLeft(10, '0');
      buffer.add(utf8.encode('$offStr 00000 n \n'));
    }

    buffer.add(utf8.encode('trailer\n<< /Size 6 /Root 1 0 R >>\n'));
    buffer.add(utf8.encode('startxref\n$xrefOffset\n%%EOF\n'));

    return buffer.toBytes();
  }

  /// Builds a valid Microsoft Word (.docx) package
  static Uint8List buildDocx({
    required Transcript transcript,
    String organization = 'PLAUD Companion Mobile',
    bool includeSummary = true,
    bool includeActions = true,
  }) {
    final paragraphs = <String>[];

    // Header & Title
    paragraphs.add(_docxHeading(transcript.title, 34, '101B33'));
    paragraphs.add(_docxMeta('$organization | ${transcript.sourceFormat} | ${transcript.recordedAt.toIso8601String().substring(0, 10)}'));
    paragraphs.add(_docxDivider());

    // Summary
    if (includeSummary && transcript.aiSummary != null) {
      paragraphs.add(_docxHeading('1. Executive Summary', 26, '18A879'));
      for (final s in transcript.aiSummary!.split('\n')) {
        if (s.trim().isNotEmpty) {
          paragraphs.add(_docxParagraph(s.trim(), false, '334155'));
        }
      }
      paragraphs.add(_docxDivider());
    }

    // Action Items
    if (includeActions && transcript.actionItems.isNotEmpty) {
      paragraphs.add(_docxHeading('2. Action Items', 26, '18A879'));
      for (final a in transcript.actionItems) {
        final check = a.isDone ? '☑' : '☐';
        final meta = a.assignee != null ? ' (${a.assignee})' : '';
        paragraphs.add(_docxBullet('$check ${a.task}$meta'));
      }
      paragraphs.add(_docxDivider());
    }

    // Diarized segments
    paragraphs.add(_docxHeading('3. Verbatim Transcript', 26, '18A879'));
    if (transcript.segments.isNotEmpty) {
      for (final seg in transcript.segments) {
        paragraphs.add(_docxSegment(seg.timeFormatted, seg.speaker, seg.text));
      }
    } else {
      for (final l in transcript.contentText.split('\n')) {
        if (l.trim().isNotEmpty) paragraphs.add(_docxParagraph(l.trim(), false));
      }
    }

    final docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        '<w:body>${paragraphs.join('')}<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr></w:body>'
        '</w:document>';

    final contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        '<Default Extension="xml" ContentType="application/xml"/>'
        '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        '</Types>';

    final rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        '</Relationships>';

    final files = <String, List<int>>{
      '[Content_Types].xml': utf8.encode(contentTypes),
      '_rels/.rels': utf8.encode(rels),
      'word/document.xml': utf8.encode(docXml),
    };

    return _createZipBytes(files);
  }

  static String _docxHeading(String text, int szVal, String color) {
    final esc = _escapeXml(text);
    return '<w:p><w:pPr><w:spacing w:before="240" w:after="120"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Helvetica"/><w:b/><w:color w:val="$color"/><w:sz w:val="$szVal"/></w:rPr><w:t>$esc</w:t></w:r></w:p>';
  }

  static String _docxMeta(String text) {
    final esc = _escapeXml(text);
    return '<w:p><w:pPr><w:spacing w:after="80"/></w:pPr><w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="18"/><w:i/></w:rPr><w:t>$esc</w:t></w:r></w:p>';
  }

  static String _docxParagraph(String text, bool bold, [String color = '000000']) {
    final esc = _escapeXml(text);
    final b = bold ? '<w:b/>' : '';
    return '<w:p><w:pPr><w:spacing w:after="100"/></w:pPr><w:r><w:rPr>$b<w:color w:val="$color"/></w:rPr><w:t xml:space="preserve">$esc</w:t></w:r></w:p>';
  }

  static String _docxBullet(String text) {
    final esc = _escapeXml(text);
    return '<w:p><w:pPr><w:pStyle w:val="ListParagraph"/><w:spacing w:after="60"/></w:pPr><w:r><w:t>• $esc</w:t></w:r></w:p>';
  }

  static String _docxSegment(String time, String speaker, String text) {
    final tEsc = _escapeXml(time);
    final sEsc = _escapeXml(speaker);
    final xEsc = _escapeXml(text);
    return '<w:p><w:pPr><w:spacing w:before="60" w:after="60"/></w:pPr>'
        '<w:r><w:rPr><w:color w:val="18A879"/><w:b/></w:rPr><w:t>[$tEsc] </w:t></w:r>'
        '<w:r><w:rPr><w:color w:val="101B33"/><w:b/></w:rPr><w:t>$sEsc: </w:t></w:r>'
        '<w:r><w:t xml:space="preserve">$xEsc</w:t></w:r></w:p>';
  }

  static String _docxDivider() {
    return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="4" w:color="E2E8F0"/></w:pBdr><w:spacing w:before="120" w:after="120"/></w:pPr></w:p>';
  }

  static String _escapeXml(String s) {
    return s.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&apos;');
  }

  /// Pure Dart ZIP archive generator (stored mode, uncompressed for 100% standard zip compliance)
  static Uint8List _createZipBytes(Map<String, List<int>> files) {
    final buffer = BytesBuilder();
    final centralDirectory = BytesBuilder();
    int localHeaderOffset = 0;

    for (final entry in files.entries) {
      final nameBytes = utf8.encode(entry.key);
      final data = entry.value;
      final crc = _computeCrc32(data);
      final size = data.length;

      // Local file header (30 bytes + name)
      final lfh = ByteData(30);
      lfh.setUint32(0, 0x04034b50, Endian.little);
      lfh.setUint16(4, 20, Endian.little); // version needed
      lfh.setUint16(6, 0, Endian.little);  // flags
      lfh.setUint16(8, 0, Endian.little);  // compression: stored (0)
      lfh.setUint16(10, 0, Endian.little); // mod time
      lfh.setUint16(12, 0, Endian.little); // mod date
      lfh.setUint32(14, crc, Endian.little);
      lfh.setUint32(18, size, Endian.little); // comp size
      lfh.setUint32(22, size, Endian.little); // uncomp size
      lfh.setUint16(26, nameBytes.length, Endian.little);
      lfh.setUint16(28, 0, Endian.little); // extra len

      buffer.add(lfh.buffer.asUint8List());
      buffer.add(nameBytes);
      buffer.add(data);

      // Central directory header (46 bytes + name)
      final cdh = ByteData(46);
      cdh.setUint32(0, 0x02014b50, Endian.little);
      cdh.setUint16(4, 20, Endian.little); // ver made by
      cdh.setUint16(6, 20, Endian.little); // ver needed
      cdh.setUint16(8, 0, Endian.little);  // flags
      cdh.setUint16(10, 0, Endian.little); // comp
      cdh.setUint16(12, 0, Endian.little); // time
      cdh.setUint16(14, 0, Endian.little); // date
      cdh.setUint32(16, crc, Endian.little);
      cdh.setUint32(20, size, Endian.little);
      cdh.setUint32(24, size, Endian.little);
      cdh.setUint16(28, nameBytes.length, Endian.little);
      cdh.setUint16(30, 0, Endian.little); // extra
      cdh.setUint16(32, 0, Endian.little); // comment
      cdh.setUint16(34, 0, Endian.little); // disk
      cdh.setUint16(36, 0, Endian.little); // int attr
      cdh.setUint32(38, 0, Endian.little); // ext attr
      cdh.setUint32(42, localHeaderOffset, Endian.little);

      centralDirectory.add(cdh.buffer.asUint8List());
      centralDirectory.add(nameBytes);

      localHeaderOffset += 30 + nameBytes.length + size;
    }

    final cdBytes = centralDirectory.toBytes();
    final cdOffset = buffer.length;
    buffer.add(cdBytes);

    // End of Central Directory Record (22 bytes)
    final eocd = ByteData(22);
    eocd.setUint32(0, 0x06054b50, Endian.little);
    eocd.setUint16(4, 0, Endian.little);
    eocd.setUint16(6, 0, Endian.little);
    eocd.setUint16(8, files.length, Endian.little);
    eocd.setUint16(10, files.length, Endian.little);
    eocd.setUint32(12, cdBytes.length, Endian.little);
    eocd.setUint32(16, cdOffset, Endian.little);
    eocd.setUint16(20, 0, Endian.little);

    buffer.add(eocd.buffer.asUint8List());
    return buffer.toBytes();
  }

  static int _computeCrc32(List<int> bytes) {
    int crc = 0xFFFFFFFF;
    for (final b in bytes) {
      crc ^= b;
      for (int i = 0; i < 8; i++) {
        if ((crc & 1) != 0) {
          crc = (crc >> 1) ^ 0xEDB88320;
        } else {
          crc = crc >> 1;
        }
      }
    }
    return (crc ^ 0xFFFFFFFF) & 0xFFFFFFFF;
  }
}
