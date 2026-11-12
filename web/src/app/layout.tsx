import type { Metadata } from "next";
import "../styles/tokens.css";

export const metadata: Metadata = {
  title: "PLAUD Companion — Admin Console",
  description: "Workspace administration, AI provider status, and job monitoring.",
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
