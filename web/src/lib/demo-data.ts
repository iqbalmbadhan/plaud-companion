export type AdminTab = "overview" | "providers" | "jobs" | "retention";

export interface DemoJobRow {
  id: string;
  type: "Import" | "AI Summary" | "Bulk Export";
  workspace: string;
  status: "Completed" | "Running" | "Queued";
  updatedAt: string;
}

export interface DemoProviderRow {
  id: string;
  name: string;
  model: string;
  keyLocation: string;
  status: "Configured (Demo)" | "Standby";
}

export const DEMO_METRICS = [
  { label: "Total Transcripts", value: "128", helper: "Across 3 workspaces" },
  { label: "Recording Time", value: "46.8h", helper: "Parsed & indexed" },
  { label: "AI Summaries", value: "94", helper: "OpenAI & Gemini" },
  { label: "Active Export Jobs", value: "2", helper: "DOCX + ZIP queue" },
];

export const DEMO_PROVIDERS: DemoProviderRow[] = [
  {
    id: "prov-openai",
    name: "OpenAI",
    model: "gpt-4o-mini / text-embedding-3-small",
    keyLocation: "Backend Vault / FastAPI ENV only",
    status: "Configured (Demo)",
  },
  {
    id: "prov-gemini",
    name: "Google Gemini",
    model: "gemini-2.5-flash / text-embedding-004",
    keyLocation: "Backend Vault / FastAPI ENV only",
    status: "Standby",
  },
];

export const DEMO_JOBS: DemoJobRow[] = [
  {
    id: "job-901",
    type: "Bulk Export",
    workspace: "Product & Engineering",
    status: "Completed",
    updatedAt: "Today, 10:15 AM",
  },
  {
    id: "job-902",
    type: "Import",
    workspace: "Product & Engineering",
    status: "Running",
    updatedAt: "Today, 2:42 PM",
  },
  {
    id: "job-903",
    type: "AI Summary",
    workspace: "Client Discovery",
    status: "Queued",
    updatedAt: "Today, 2:45 PM",
  },
];
