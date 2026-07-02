/**
 * Custom AI provider — OpenAI-compatible chat completions endpoint.
 * Values come from admin-editable site settings (which fall back to env vars),
 * so users can plug in OpenAI, OpenRouter, Groq, Together, Ollama, Lovable AI,
 * or any other compatible host from the admin UI without redeploying.
 */
import { readSettings } from "./admin-session.server";

export type AiConfig = {
  baseUrl: string;
  apiKey: string;
  model: string;
  systemPrompt: string;
};

export async function getAiConfig(): Promise<AiConfig> {
  const s = await readSettings();
  return {
    baseUrl: (s.aiBaseUrl || "https://ai.gateway.lovable.dev/v1").replace(/\/$/, ""),
    apiKey: s.aiApiKey || process.env.LOVABLE_API_KEY || process.env.OPENAI_API_KEY || "",
    model: s.aiModel || "google/gemini-2.5-flash",
    systemPrompt:
      s.aiSystemPrompt ||
      "You are a senior affiliate-review editor writing SEO-optimized, trustworthy product roundups.",
  };
}

export async function aiChat(
  userPrompt: string,
  opts?: { systemPrompt?: string; model?: string; temperature?: number },
): Promise<string> {
  const cfg = await getAiConfig();
  if (!cfg.apiKey) {
    throw new Error(
      "AI provider not configured. Set the AI API key in Admin → Settings → AI Provider.",
    );
  }
  const res = await fetch(`${cfg.baseUrl}/chat/completions`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${cfg.apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      model: opts?.model || cfg.model,
      temperature: opts?.temperature ?? 0.7,
      messages: [
        { role: "system", content: opts?.systemPrompt || cfg.systemPrompt },
        { role: "user", content: userPrompt },
      ],
    }),
  });
  if (!res.ok) {
    throw new Error(`AI provider ${res.status}: ${(await res.text()).slice(0, 300)}`);
  }
  const json = (await res.json()) as {
    choices?: Array<{ message?: { content?: string } }>;
  };
  return json.choices?.[0]?.message?.content ?? "";
}

/** Quick health check for the admin UI. */
export async function testAiConfig(): Promise<
  { ok: true; model: string; latency_ms: number } | { ok: false; error: string }
> {
  const started = Date.now();
  try {
    const cfg = await getAiConfig();
    if (!cfg.apiKey) return { ok: false, error: "No API key configured" };
    const reply = await aiChat("Reply with the single word: pong.", { temperature: 0 });
    return { ok: true, model: cfg.model, latency_ms: Date.now() - started };
  } catch (e) {
    return { ok: false, error: e instanceof Error ? e.message : "test failed" };
  }
}
