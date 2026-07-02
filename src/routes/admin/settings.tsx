import { createFileRoute } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useEffect, useState } from "react";
import { getSiteSettings, updateSiteSettings, testAiProvider } from "@/lib/admin.functions";
import { testLambda } from "@/lib/products.functions";

export const Route = createFileRoute("/admin/settings")({
  component: SettingsPage,
});

type Settings = Awaited<ReturnType<typeof getSiteSettings>>;

const empty: Settings = {
  headCode: "",
  gaId: "",
  metaTitle: "",
  metaDescription: "",
  siteUrl: "",
  lambdaUrl: "",
  amazonTag: "",
  paapiAccessKey: "",
  paapiSecretKey: "",
  paapiPartnerTag: "",
  aiBaseUrl: "",
  aiApiKey: "",
  aiModel: "",
  aiSystemPrompt: "",
  autopilotEnabled: "0",
  autopilotBatchSize: "3",
  autopilotIntervalMin: "60",
  cronSecret: "",
  priceCacheTtlMin: "60",
};

function SettingsPage() {
  const load = useServerFn(getSiteSettings);
  const save = useServerFn(updateSiteSettings);
  const test = useServerFn(testLambda);
  const testAi = useServerFn(testAiProvider);
  const [state, setState] = useState<Settings>(empty);
  const [saved, setSaved] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [testResult, setTestResult] = useState<Awaited<ReturnType<typeof testLambda>> | null>(null);
  const [testing, setTesting] = useState(false);
  const [aiTestResult, setAiTestResult] = useState<Awaited<ReturnType<typeof testAiProvider>> | null>(null);
  const [aiTesting, setAiTesting] = useState(false);

  useEffect(() => {
    load().then(setState);
  }, [load]);

  async function onSave(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setSaved(null);
    const next = await save({ data: state });
    setState(next);
    setSaved("Saved.");
    setBusy(false);
  }

  async function onTest() {
    setTesting(true);
    setTestResult(null);
    try {
      const r = await test({});
      setTestResult(r);
    } finally {
      setTesting(false);
    }
  }

  async function onTestAi() {
    setAiTesting(true);
    setAiTestResult(null);
    try {
      const r = await testAi({});
      setAiTestResult(r);
    } finally {
      setAiTesting(false);
    }
  }

  const update = <K extends keyof Settings>(k: K, v: Settings[K]) =>
    setState((s) => ({ ...s, [k]: v }));

  const cronUrl =
    typeof window !== "undefined" ? `${window.location.origin}/api/public/autopilot/run` : "";

  return (
    <form onSubmit={onSave} className="space-y-8">
      <Group
        title="Site identity"
        desc="Canonical URL is used for sitemap.xml, canonical tags, and og:url. Leave blank to auto-detect from request."
      >
        <label className="block">
          <span className="text-sm font-medium">Canonical site URL</span>
          <input
            className="input"
            value={state.siteUrl}
            onChange={(e) => update("siteUrl", e.target.value)}
            placeholder="https://yadfoods.com"
          />
        </label>
      </Group>

      <Group
        title="AI Provider (OpenAI-compatible)"
        desc="Plug in any OpenAI-compatible endpoint: OpenAI, OpenRouter, Groq, Together, Anthropic proxy, Ollama, Lovable AI Gateway, etc. Used for bulk generate + autopilot."
      >
        <label className="block">
          <span className="text-sm font-medium">Base URL</span>
          <input
            className="input font-mono text-xs"
            value={state.aiBaseUrl}
            onChange={(e) => update("aiBaseUrl", e.target.value)}
            placeholder="https://api.openai.com/v1"
          />
          <span className="mt-1 block text-[11px] text-muted-foreground">
            Must end in <code>/v1</code>. Examples: <code>https://api.openai.com/v1</code>,{" "}
            <code>https://openrouter.ai/api/v1</code>,{" "}
            <code>https://ai.gateway.lovable.dev/v1</code>
          </span>
        </label>
        <label className="block">
          <span className="text-sm font-medium">API Key</span>
          <input
            className="input font-mono text-xs"
            type="password"
            value={state.aiApiKey}
            onChange={(e) => update("aiApiKey", e.target.value)}
            placeholder="sk-…"
          />
        </label>
        <label className="block">
          <span className="text-sm font-medium">Model</span>
          <input
            className="input font-mono text-xs"
            value={state.aiModel}
            onChange={(e) => update("aiModel", e.target.value)}
            placeholder="gpt-4o-mini"
          />
          <span className="mt-1 block text-[11px] text-muted-foreground">
            e.g. <code>gpt-4o-mini</code>, <code>openai/gpt-4o-mini</code>,{" "}
            <code>google/gemini-2.5-flash</code>, <code>anthropic/claude-3.5-haiku</code>
          </span>
        </label>
        <label className="block">
          <span className="text-sm font-medium">System prompt</span>
          <textarea
            className="input h-24"
            value={state.aiSystemPrompt}
            onChange={(e) => update("aiSystemPrompt", e.target.value)}
          />
        </label>
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={onTestAi}
            disabled={aiTesting}
            className="rounded-full border border-border bg-card px-4 py-1.5 text-xs font-semibold hover:bg-muted disabled:opacity-60"
          >
            {aiTesting ? "Testing…" : "Test AI connection"}
          </button>
          {aiTestResult && (
            <span className="text-xs">
              {aiTestResult.ok ? (
                <span className="text-green-700">
                  ✓ {aiTestResult.model} · {aiTestResult.latency_ms}ms
                </span>
              ) : (
                <span className="text-destructive">✗ {aiTestResult.error}</span>
              )}
            </span>
          )}
        </div>
      </Group>

      <Group
        title="Product data source"
        desc="Live cURL endpoint that returns products. All prices, ratings, and images are fetched live — nothing is stored."
      >
        <label className="block">
          <span className="text-sm font-medium">Lambda / product API URL</span>
          <input
            className="input"
            value={state.lambdaUrl}
            onChange={(e) => update("lambdaUrl", e.target.value)}
            placeholder="https://…lambda-url.us-east-1.on.aws/"
          />
        </label>
        <label className="block">
          <span className="text-sm font-medium">Amazon Associates tag</span>
          <input
            className="input"
            value={state.amazonTag}
            onChange={(e) => update("amazonTag", e.target.value)}
            placeholder="YOUR-TAG-20"
          />
        </label>
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={onTest}
            disabled={testing}
            className="rounded-full border border-border bg-card px-4 py-1.5 text-xs font-semibold hover:bg-muted disabled:opacity-60"
          >
            {testing ? "Testing…" : "Test connection"}
          </button>
          {testResult && (
            <span className="text-xs">
              {testResult.ok ? (
                <span className="text-green-700">
                  ✓ {testResult.count} products · {testResult.latency_ms}ms
                </span>
              ) : (
                <span className="text-destructive">✗ {testResult.error}</span>
              )}
            </span>
          )}
        </div>
      </Group>

      <Group
        title="Amazon PA-API (optional fallback)"
        desc="Real Amazon Product Advertising API credentials. Used only when the Lambda source fails. Leave blank to skip."
      >
        <label className="block">
          <span className="text-sm font-medium">Access Key</span>
          <input
            className="input font-mono"
            value={state.paapiAccessKey}
            onChange={(e) => update("paapiAccessKey", e.target.value)}
          />
        </label>
        <label className="block">
          <span className="text-sm font-medium">Secret Key</span>
          <input
            className="input font-mono"
            type="password"
            value={state.paapiSecretKey}
            onChange={(e) => update("paapiSecretKey", e.target.value)}
          />
        </label>
        <label className="block">
          <span className="text-sm font-medium">Partner Tag</span>
          <input
            className="input"
            value={state.paapiPartnerTag}
            onChange={(e) => update("paapiPartnerTag", e.target.value)}
          />
        </label>
      </Group>

      <Group
        title="Autopilot & Cron"
        desc="Background worker that turns queued keywords into review drafts. Point an external cron at the URL below at your chosen interval."
      >
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={state.autopilotEnabled === "1"}
            onChange={(e) => update("autopilotEnabled", e.target.checked ? "1" : "0")}
          />
          <span className="text-sm font-medium">Enable autopilot cron</span>
        </label>
        <div className="grid gap-4 sm:grid-cols-3">
          <label className="block">
            <span className="text-sm font-medium">Batch size (per run)</span>
            <input
              className="input"
              type="number"
              min={1}
              max={20}
              value={state.autopilotBatchSize}
              onChange={(e) => update("autopilotBatchSize", e.target.value)}
            />
          </label>
          <label className="block">
            <span className="text-sm font-medium">Interval (minutes)</span>
            <input
              className="input"
              type="number"
              min={5}
              max={1440}
              value={state.autopilotIntervalMin}
              onChange={(e) => update("autopilotIntervalMin", e.target.value)}
            />
          </label>
          <label className="block">
            <span className="text-sm font-medium">Price cache TTL (min)</span>
            <input
              className="input"
              type="number"
              min={1}
              max={1440}
              value={state.priceCacheTtlMin}
              onChange={(e) => update("priceCacheTtlMin", e.target.value)}
            />
          </label>
        </div>
        <label className="block">
          <span className="text-sm font-medium">Cron secret</span>
          <input
            className="input font-mono text-xs"
            value={state.cronSecret}
            onChange={(e) => update("cronSecret", e.target.value)}
            placeholder="Set any random string — required to trigger cron"
          />
          <button
            type="button"
            onClick={() =>
              update(
                "cronSecret",
                Array.from(crypto.getRandomValues(new Uint8Array(24)))
                  .map((b) => b.toString(16).padStart(2, "0"))
                  .join(""),
              )
            }
            className="mt-1 text-[11px] text-amber underline"
          >
            Generate random
          </button>
        </label>
        <div className="rounded-md border border-border bg-muted/40 p-3 text-xs">
          <div className="font-semibold">Cron endpoint</div>
          <code className="mt-1 block break-all font-mono">POST {cronUrl}</code>
          <div className="mt-1">
            Header: <code>X-Cron-Secret: &lt;your secret&gt;</code>
          </div>
          <div className="mt-1 text-muted-foreground">
            Use cron-job.org, EasyCron, GitHub Actions, or your VPS crontab to POST at your chosen
            interval.
          </div>
        </div>
      </Group>

      <Group title="SEO & analytics">
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block">
            <span className="text-sm font-medium">Default meta title</span>
            <input
              className="input"
              value={state.metaTitle}
              onChange={(e) => update("metaTitle", e.target.value)}
            />
          </label>
          <label className="block">
            <span className="text-sm font-medium">Google Analytics ID (GA4)</span>
            <input
              className="input"
              value={state.gaId}
              onChange={(e) => update("gaId", e.target.value)}
              placeholder="G-XXXXXXXXXX"
            />
          </label>
        </div>
        <label className="block">
          <span className="text-sm font-medium">Default meta description</span>
          <textarea
            className="input h-20"
            value={state.metaDescription}
            onChange={(e) => update("metaDescription", e.target.value)}
          />
        </label>
        <label className="block">
          <span className="text-sm font-medium">Custom &lt;head&gt; code</span>
          <textarea
            className="input h-32 font-mono text-xs"
            value={state.headCode}
            onChange={(e) => update("headCode", e.target.value)}
            placeholder='<meta name="google-site-verification" content="..." />'
          />
        </label>
      </Group>

      <div className="sticky bottom-4 flex items-center gap-3 rounded-full border border-border bg-background/90 px-4 py-3 shadow-md backdrop-blur">
        <button
          type="submit"
          disabled={busy}
          className="rounded-full bg-amber px-5 py-2 text-sm font-semibold text-white shadow hover:bg-amber/90 disabled:opacity-60"
        >
          {busy ? "Saving…" : "Save all settings"}
        </button>
        {saved && <span className="text-sm text-green-700">{saved}</span>}
      </div>

      <style>{`.input{margin-top:0.25rem;width:100%;border-radius:0.375rem;border:1px solid hsl(var(--input));background:hsl(var(--background));padding:0.5rem 0.75rem;font-size:0.875rem;box-shadow:0 1px 2px 0 rgb(0 0 0 / 0.05)}`}</style>
    </form>
  );
}

function Group({
  title,
  desc,
  children,
}: {
  title: string;
  desc?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="rounded-lg border border-border bg-card p-5">
      <h2 className="font-serif text-lg">{title}</h2>
      {desc && <p className="mt-1 text-xs text-muted-foreground">{desc}</p>}
      <div className="mt-4 space-y-4">{children}</div>
    </section>
  );
}
