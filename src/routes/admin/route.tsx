import { createFileRoute, Link, Outlet, redirect, useRouter } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { adminLogout, adminMe } from "@/lib/admin.functions";

export const Route = createFileRoute("/admin")({
  ssr: false,
  beforeLoad: async () => {
    const me = await adminMe();
    if (!me.authed) throw redirect({ to: "/logmein" });
    return { admin: me };
  },
  head: () => ({ meta: [{ title: "Admin Dashboard" }, { name: "robots", content: "noindex,nofollow" }] }),
  component: AdminLayout,
});

function AdminLayout() {
  const router = useRouter();
  const logout = useServerFn(adminLogout);

  async function handleLogout() {
    await logout({});
    router.navigate({ to: "/logmein" });
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
        <div>
          <h1 className="font-serif text-2xl">Admin Dashboard</h1>
          <p className="text-xs text-muted-foreground">yadfoods.com control panel</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={handleLogout}
            className="rounded-full border border-border bg-card px-4 py-1.5 text-sm font-medium hover:bg-muted"
          >
            Sign out
          </button>
        </div>
      </div>
      <nav className="mt-4 flex flex-wrap gap-1 border-b border-border">
        {[
          { to: "/admin", label: "Overview" },
          { to: "/admin/settings", label: "Site Settings" },
          { to: "/admin/generate", label: "Bulk Generate" },
          { to: "/admin/autopilot", label: "Autopilot" },
          { to: "/admin/reviews", label: "Reviews" },
          { to: "/admin/analytics", label: "Analytics" },
        ].map((t) => (
          <Link
            key={t.to}
            to={t.to}
            activeOptions={{ exact: t.to === "/admin" }}
            className="border-b-2 border-transparent px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground [&.active]:border-amber [&.active]:text-foreground"
          >
            {t.label}
          </Link>
        ))}
      </nav>
      <div className="mt-6">
        <Outlet />
      </div>
    </div>
  );
}
