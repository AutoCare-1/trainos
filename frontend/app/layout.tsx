import type { Metadata, Viewport } from "next";
import { Geist, Geist_Mono, Bricolage_Grotesque } from "next/font/google";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

// Fonte de exibição — usada só em títulos/números de destaque, nunca no corpo
// de texto, pra dar uma identidade tipográfica própria (ver app/globals.css).
const displayFont = Bricolage_Grotesque({
  variable: "--font-display",
  subsets: ["latin"],
  weight: ["600", "700", "800"],
});

export const metadata: Metadata = {
  title: "Clube Mais - Personal",
  description: "Gestão de alunos e treinos — Clube Mais Personal",
  manifest: "/manifest.json",
  icons: {
    icon: "/clubemais-icone.png",
    apple: "/apple-touch-icon.png",
  },
  appleWebApp: {
    capable: true,
    statusBarStyle: "default",
    title: "Clube Mais",
  },
};

export const viewport: Viewport = {
  themeColor: "#2648b3",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="pt-BR"
      className={`${geistSans.variable} ${geistMono.variable} ${displayFont.variable} h-full antialiased`}
    >
      <body className="min-h-screen flex flex-col bg-glow">{children}</body>
    </html>
  );
}
