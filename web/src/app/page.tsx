"use client";

import { useState } from "react";
import {
  AdminTab,
  DEMO_JOBS,
  DEMO_METRICS,
  DEMO_PROVIDERS,
} from "../lib/demo-data";

const NAV_ITEMS: { id: AdminTab; label: string }[] = [
  { id: "overview", label: "Overview & Usage" },
  { id: "providers", label: "AI Providers" },
  { id: "jobs", label: "Background Jobs" },
  { id: "retention", label: "Retention & Security" },
];

export default function AdminDashboardPage() {
  const [activeTab, setActiveTab] = useState<AdminTab>("overview");
  const [retentionDays, setRetentionDays] = useState<number>(90);

  return (
    <div className="admin-layout">
      <aside className="sidebar" aria-label="Admin navigation">
        <div className="brand">
          <span className="brand-dot" aria-hidden="true" />
          <span>PLAUD Admin</span>
        </div>
        {NAV_ITEMS.map((item) => (
          <button
            key={item.id}
            type="button"
            className={`nav-btn ${activeTab === item.id ? "active" : ""}`}
            onClick={() => setActiveTab(item.id)}
          >
            {item.label}
          </button>
        ))}
      </aside>

      <main className="main-content">
        <div className="demo-banner" role="status">
          DEMO DATA · Phase 1 Admin Web Shell (no live FastAPI backend or API keys connected)
        </div>

        <div className="header-row">
          <div>
            <h1 style={{ margin: 0, fontSize: 24 }}>
              {NAV_ITEMS.find((n) => n.id === activeTab)?.label}
            </h1>
            <p style={{ margin: "4px 0 0", color: "var(--secondary-text)" }}>
              Workspace administration and operational telemetry
            </p>
          </div>
          <span className="badge">Workspace: Product &amp; Engineering</span>
        </div>

        {activeTab === "overview" && (
          <>
            <section className="grid-3" aria-label="Usage metrics">
              {DEMO_METRICS.map((m) => (
                <div key={m.label} className="card">
                  <div className="stat-label">{m.label}</div>
                  <div className="stat-value">{m.value}</div>
                  <div style={{ fontSize: 12, color: "var(--secondary-text)", marginTop: 4 }}>
                    {m.helper}
                  </div>
                </div>
              ))}
            </section>

            <section className="card">
              <h2 style={{ marginTop: 0, fontSize: 18 }}>Recent Background Jobs</h2>
              <table className="table">
                <thead>
                  <tr>
                    <th>Job ID</th>
                    <th>Type</th>
                    <th>Workspace</th>
                    <th>Status</th>
                    <th>Updated</th>
                  </tr>
                </thead>
                <tbody>
                  {DEMO_JOBS.map((job) => (
                    <tr key={job.id}>
                      <td>{job.id}</td>
                      <td>{job.type}</td>
                      <td>{job.workspace}</td>
                      <td>
                        <span
                          className={`badge ${
                            job.status === "Completed" ? "" : "badge-warn"
                          }`}
                        >
                          {job.status}
                        </span>
                      </td>
                      <td>{job.updatedAt}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </section>
          </>
        )}

        {activeTab === "providers" && (
          <section className="card">
            <h2 style={{ marginTop: 0, fontSize: 18 }}>
              Backend-Only AI Provider Adapters
            </h2>
            <p style={{ color: "var(--secondary-text)", fontSize: 14 }}>
              Per AGENTS.md security rules, provider API keys are never stored or exposed in browser bundles.
            </p>
            <table className="table">
              <thead>
                <tr>
                  <th>Provider</th>
                  <th>Default Models</th>
                  <th>Credential Scope</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {DEMO_PROVIDERS.map((p) => (
                  <tr key={p.id}>
                    <td><strong>{p.name}</strong></td>
                    <td>{p.model}</td>
                    <td>{p.keyLocation}</td>
                    <td><span className="badge">{p.status}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        )}

        {activeTab === "jobs" && (
          <section className="card">
            <h2 style={{ marginTop: 0, fontSize: 18 }}>Import &amp; Export Worker Queue</h2>
            <p style={{ color: "var(--secondary-text)", fontSize: 14 }}>
              Idempotent Redis + Celery/ARQ jobs will connect here in Phase 2–6.
            </p>
            <table className="table">
              <thead>
                <tr>
                  <th>Job ID</th>
                  <th>Type</th>
                  <th>Workspace</th>
                  <th>Status</th>
                  <th>Updated</th>
                </tr>
              </thead>
              <tbody>
                {DEMO_JOBS.map((job) => (
                  <tr key={job.id}>
                    <td>{job.id}</td>
                    <td>{job.type}</td>
                    <td>{job.workspace}</td>
                    <td><span className="badge">{job.status}</span></td>
                    <td>{job.updatedAt}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        )}

        {activeTab === "retention" && (
          <section className="card">
            <h2 style={{ marginTop: 0, fontSize: 18 }}>Data Retention &amp; Privacy Controls</h2>
            <p style={{ color: "var(--secondary-text)", fontSize: 14 }}>
              Configure workspace transcript retention windows and audit logging policies.
            </p>
            <label htmlFor="retention-select" style={{ display: "block", fontWeight: 600, marginBottom: 8 }}>
              Transcript Retention Window (Days)
            </label>
            <select
              id="retention-select"
              value={retentionDays}
              onChange={(e) => setRetentionDays(Number(e.target.value))}
              style={{
                padding: "10px 12px",
                borderRadius: 8,
                border: "1px solid var(--border-color)",
                fontSize: 14,
              }}
            >
              <option value={30}>30 days</option>
              <option value={90}>90 days (Recommended)</option>
              <option value={365}>365 days</option>
            </select>
            <p style={{ marginTop: 12, fontSize: 13, color: "var(--secondary-text)" }}>
              Current demo policy: Retain exported manifests and transcripts for{" "}
              <strong>{retentionDays} days</strong> before scheduled purge.
            </p>
          </section>
        )}
      </main>
    </div>
  );
}
