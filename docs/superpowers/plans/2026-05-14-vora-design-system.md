# Vora Design System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Vora design-system foundation — tokens (light+dark), shadcn-vue component library, and the app shell (collapsible navy sidebar rail, header, layout).

**Architecture:** Adopt `shadcn-vue` as the primitive layer (CLI copies component source into `resources/js/Components/ui/`, replacing the existing hand-rolled ones). Vora-specific composed components live in `resources/js/Components/vora/`. The shell lives in `resources/js/Layouts/`. Tokens are HSL CSS vars in `resources/css/app.css` (light under `:root`, dark under `.dark`), consumed through `tailwind.config.js`.

**Tech Stack:** Laravel 11 · Inertia · Vue 3 · Tailwind 3 · shadcn-vue · reka-ui · class-variance-authority · lucide-vue-next · @vueuse/core

**Working directory for all commands:** `C:\Users\canal\Downloads\teste gerenciamento whatsapp\app`

**Verification note:** No JS test framework or Playwright in this environment. "Tests" = `npm run build` passing clean, the dev server responding, and the `/_ui` showcase route rendering every component. Each task ends by building and committing.

---

## File Structure

| File | Responsibility |
|---|---|
| `resources/css/app.css` | Token definitions — `:root` (light) + `.dark` (dark), base layer |
| `tailwind.config.js` | Token wiring, `vora.*` + `sidebar.*` raw tokens, fonts, shadows |
| `components.json` | shadcn-vue CLI config |
| `resources/js/lib/utils.js` | `cn()` helper (already exists — leave as-is) |
| `resources/js/Components/ui/*` | shadcn-vue primitives (CLI-generated, replaces hand-rolled) |
| `resources/js/Components/vora/MetricCard.vue` | KPI card (label, value, delta, icon) |
| `resources/js/Components/vora/ChartCard.vue` | Titled container/shell for a chart |
| `resources/js/Components/vora/EmptyState.vue` | Empty-state block (icon, title, text, slot for action) |
| `resources/js/Components/vora/LoadingSkeleton.vue` | Page-level skeleton compositions |
| `resources/js/Components/vora/DataTable.vue` | Table wrapper with column defs + sort + pagination |
| `resources/js/Components/vora/PageHeader.vue` | Page title + breadcrumb + actions slot |
| `resources/js/Components/vora/UserMenu.vue` | Avatar + dropdown (profile, theme, logout) |
| `resources/js/Components/vora/ThemeToggle.vue` | Light/dark switch, persists to localStorage |
| `resources/js/composables/useTheme.js` | Theme state + persistence + `<html>.dark` toggling |
| `resources/js/Layouts/Sidebar.vue` | Navy collapsible icon rail |
| `resources/js/Layouts/Header.vue` | White top bar (page title, search, theme, notifications, user menu) |
| `resources/js/Layouts/AppLayout.vue` | Composes Sidebar + Header + Toaster + content slot |
| `resources/js/Pages/_Ui/Index.vue` | Dev-only component showcase |
| `routes/web.php` | Add dev-only `/_ui` route |

---

## Task 0: Git baseline

**Files:**
- Create: `C:\Users\canal\Downloads\teste gerenciamento whatsapp\.gitignore`

- [ ] **Step 1: Initialize git at the project root**

Run (from project root `C:\Users\canal\Downloads\teste gerenciamento whatsapp`):
```bash
git init
```
Expected: `Initialized empty Git repository`

- [ ] **Step 2: Create root `.gitignore`**

Create `C:\Users\canal\Downloads\teste gerenciamento whatsapp\.gitignore`:
```
/app/node_modules
/app/vendor
/app/public/build
/app/public/hot
/app/storage/*.key
/app/storage/logs/*
/app/storage/framework/cache/*
/app/storage/framework/sessions/*
/app/storage/framework/views/*
/app/.env
/app/database/database.sqlite
.superpowers/
ngrok.log
n8n_cookies.txt
```

- [ ] **Step 3: Baseline commit**

Run (from project root):
```bash
git add -A
git commit -m "chore: baseline before Vora design system"
```
Expected: a commit is created with the current project state.

---

## Task 1: Install dependencies & init shadcn-vue

**Files:**
- Create: `app/components.json`

- [ ] **Step 1: Install missing runtime deps**

Run (from `app/`):
```bash
npm install reka-ui @vueuse/core
```
Expected: both added to `package.json` dependencies, no errors. (`class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-vue-next` are already present.)

- [ ] **Step 2: Create `app/components.json`**

```json
{
  "$schema": "https://shadcn-vue.com/schema.json",
  "style": "default",
  "typescript": false,
  "tailwind": {
    "config": "tailwind.config.js",
    "css": "resources/css/app.css",
    "baseColor": "slate",
    "cssVariables": true
  },
  "aliases": {
    "components": "@/Components",
    "composables": "@/composables",
    "utils": "@/lib/utils",
    "ui": "@/Components/ui",
    "lib": "@/lib"
  },
  "iconLibrary": "lucide"
}
```

- [ ] **Step 3: Confirm the `@` alias resolves to `resources/js`**

Read `app/vite.config.js`. If there is no `resolve.alias` mapping `@` → `resources/js`, add it:
```js
import { fileURLToPath, URL } from 'node:url';
// inside defineConfig({ ... }):
resolve: {
    alias: {
        '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
},
```
If the alias already exists, leave the file unchanged.

- [ ] **Step 4: Build to confirm nothing broke**

Run (from `app/`):
```bash
npm run build
```
Expected: `✓ built` with no errors.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: install shadcn-vue deps and components.json"
```

---

## Task 2: Design tokens (light + dark)

**Files:**
- Modify: `app/resources/css/app.css`
- Modify: `app/tailwind.config.js`

- [ ] **Step 1: Replace the `:root` token block and add `.dark` in `app/resources/css/app.css`**

Replace lines 5–37 (the `@layer base { :root { ... } }` token section) so the `@layer base` block begins like this (keep everything from `* { @apply border-border; }` onward unchanged):
```css
@layer base {
    :root {
        --background:             220 14% 97%;
        --foreground:             218 59% 8%;
        --card:                   0 0% 100%;
        --card-foreground:        218 59% 8%;
        --popover:                0 0% 100%;
        --popover-foreground:     218 59% 8%;
        --primary:                11 87% 54%;
        --primary-foreground:     0 0% 100%;
        --secondary:              220 14% 95%;
        --secondary-foreground:   218 59% 8%;
        --muted:                  220 14% 95%;
        --muted-foreground:       222 13% 46%;
        --accent:                 220 13% 94%;
        --accent-foreground:      218 59% 8%;
        --destructive:            358 75% 59%;
        --destructive-foreground: 0 0% 100%;
        --border:                 220 13% 91%;
        --input:                  220 13% 91%;
        --ring:                   11 87% 54%;
        --radius:                 0.625rem;
    }

    .dark {
        --background:             219 69% 9%;
        --foreground:             220 20% 92%;
        --card:                   220 54% 13%;
        --card-foreground:        220 20% 92%;
        --popover:                220 54% 13%;
        --popover-foreground:     220 20% 92%;
        --primary:                11 87% 54%;
        --primary-foreground:     0 0% 100%;
        --secondary:              220 45% 16%;
        --secondary-foreground:   220 20% 92%;
        --muted:                  220 45% 16%;
        --muted-foreground:       220 14% 65%;
        --accent:                 222 38% 19%;
        --accent-foreground:      220 20% 92%;
        --destructive:            358 85% 64%;
        --destructive-foreground: 0 0% 100%;
        --border:                 222 38% 20%;
        --input:                  222 38% 20%;
        --ring:                   11 87% 54%;
        --radius:                 0.625rem;
    }
```

- [ ] **Step 2: Update the brand `@layer components` blocks in `app/resources/css/app.css`**

Replace the `.bg-vora-gradient` and `.bg-vora-mark` rules (around lines 84–90) with:
```css
    .bg-vora-mark {
        background: #071225;
    }
```
(Delete `.bg-vora-gradient` entirely — strong gradients are out per the brand rules.)

- [ ] **Step 3: Update `app/tailwind.config.js` — `vora` + `sidebar` tokens, fonts, shadows**

In `theme.extend.colors`, replace the `vora` and `sidebar` blocks with:
```js
                vora: {
                    navy:        '#071225',
                    'navy-deep': '#0F1B33',
                    orange:      '#F04A24',
                    'orange-light': '#FF7A3D',
                    bg:          '#F6F7F9',
                    border:      '#E5E7EB',
                    text:        '#08111F',
                    muted:       '#667085',
                },
                sidebar: {
                    DEFAULT:    '#071225',
                    foreground: '#C7CDD9',
                    muted:      '#8A93A6',
                    accent:     '#0F1B33',
                    active:     '#F04A24',
                    border:     '#1E2A47',
                },
```

In `theme.extend.fontFamily`, remove the `display` entry's `"Cal Sans"` so it reads:
```js
                display: ['Inter', '"Inter var"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
```

In `theme.extend.boxShadow`, delete the `accent` line (the orange glow). Keep `soft`, `card`, `pop`, `ring`.

- [ ] **Step 4: Build**

Run (from `app/`): `npm run build`
Expected: `✓ built`, no errors.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: Vora design tokens with light and dark themes"
```

---

## Task 3: Generate shadcn-vue primitives

**Files:**
- Replace: `app/resources/js/Components/ui/*` (CLI overwrites; confirm overwrite when prompted)

- [ ] **Step 1: Generate the primitive set**

Run (from `app/`), accepting overwrite prompts:
```bash
npx shadcn-vue@latest add button input textarea label select checkbox switch badge avatar dialog sheet tabs dropdown-menu tooltip popover table skeleton sonner scroll-area separator command card --overwrite
```
Expected: components written under `resources/js/Components/ui/<name>/`. Each is a folder with an `index.js` barrel + `.vue` files.

- [ ] **Step 2: Remove stale hand-rolled flat files**

The old library used flat files (`Components/ui/Button.vue`, `Card.vue`, etc.). shadcn-vue writes folders. Delete any leftover flat `.vue` files in `resources/js/Components/ui/` that are NOT inside a component folder:
```bash
find resources/js/Components/ui -maxdepth 1 -name "*.vue" -delete
```
Also delete the duplicate `resources/js/Components/Avatar.vue` and `resources/js/Components/Badge.vue` (they shadow the ui ones and are unused after migration — verified in Task 4).

- [ ] **Step 3: Add the Toaster mount requirement note**

`sonner` provides `<Toaster />` from `@/Components/ui/sonner`. It will be mounted in `AppLayout.vue` (Task 6). No action here beyond confirming the `sonner` folder exists.

- [ ] **Step 4: Build (expected to FAIL)**

Run (from `app/`): `npm run build`
Expected: **FAILS** — existing pages still import old paths like `@/Components/ui/Button`. This failure is the entry point for Task 4.

---

## Task 4: Compile-fix migration

**Files (modify — fix imports/usages until build passes):**
- `app/resources/js/Pages/Conversations/Index.vue`
- `app/resources/js/Pages/Auth/Login/Index.vue`
- `app/resources/js/Pages/Dashboard/Index.vue`
- `app/resources/js/Pages/Sectors/Index.vue`
- `app/resources/js/Pages/Users/Index.vue`
- `app/resources/js/Pages/Settings/Index.vue`
- `app/resources/js/Components/TicketListItem.vue`
- `app/resources/js/Components/MessageBubble.vue`
- `app/resources/js/Components/ClientPanel.vue`
- `app/resources/js/Components/Sidebar.vue` (old — will be superseded in Task 6, but must compile until then)
- `app/resources/js/Components/Topbar.vue` (old — same)
- `app/resources/js/Layouts/GuestLayout.vue`

- [ ] **Step 1: Find every importer of the old `ui/` components**

Run (from `app/`):
```bash
grep -rn "Components/ui/" resources/js --include=*.vue
grep -rn "Components/Avatar\|Components/Badge" resources/js --include=*.vue
```
Record every file + line. These are the migration sites.

- [ ] **Step 2: Rewrite imports to shadcn-vue barrel paths**

shadcn-vue exports are named, from the folder barrel. Apply these mappings in every file found in Step 1:

| Old import | New import |
|---|---|
| `import Button from '@/Components/ui/Button.vue'` | `import { Button } from '@/Components/ui/button'` |
| `import Input from '@/Components/ui/Input.vue'` | `import { Input } from '@/Components/ui/input'` |
| `import Textarea from '@/Components/ui/Textarea.vue'` | `import { Textarea } from '@/Components/ui/textarea'` |
| `import Badge from '@/Components/ui/Badge.vue'` (or `@/Components/Badge.vue`) | `import { Badge } from '@/Components/ui/badge'` |
| `import Avatar from '@/Components/ui/Avatar.vue'` (or `@/Components/Avatar.vue`) | `import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'` |
| `import Dialog from '@/Components/ui/Dialog.vue'` | `import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'` |
| `import Separator from '@/Components/ui/Separator.vue'` | `import { Separator } from '@/Components/ui/separator'` |
| `import Skeleton from '@/Components/ui/Skeleton.vue'` | `import { Skeleton } from '@/Components/ui/skeleton'` |
| `import Card from '@/Components/ui/Card.vue'` (+ Card* subcomponents) | `import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/Components/ui/card'` |

- [ ] **Step 3: Fix usage API differences**

Known prop/slot differences to apply where each component is used:
- **Avatar:** old usage `<Avatar :name="x" />` → new usage:
  ```vue
  <Avatar><AvatarFallback>{{ initials(x) }}</AvatarFallback></Avatar>
  ```
  Add a local `initials(name)` helper where needed: `name.split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase()`.
- **Badge:** old `variant` values (`success`, `warning`, `info`, `muted`) are not in shadcn-vue's set (`default`, `secondary`, `destructive`, `outline`). Map: `success`/`info` → `default`, `warning` → `secondary`, `muted` → `outline`. (Proper status colors are an SP4/SP5 concern; this is compile-fix only.)
- **Button:** shadcn-vue `Button` supports `variant` (`default|secondary|outline|ghost|destructive|link`) and `size` (`default|sm|lg|icon`). Old `variant="accent"` → `variant="default"`. Old `variant="ghost"` and `size="icon"` are valid — keep.
- **Dialog:** old single-component dialog → shadcn-vue compositional. Wrap existing modal content in `<Dialog v-model:open="..."><DialogContent>...</DialogContent></Dialog>`.

- [ ] **Step 4: Build until clean**

Run (from `app/`): `npm run build`
Iterate Steps 2–3 on any remaining error until: `✓ built`, no errors.

- [ ] **Step 5: Smoke-check routes**

Ensure the dev server is running (`php artisan serve` on :8000). Then run:
```bash
for r in / /conversations /dashboard /sectors /users /settings; do
  echo -n "$r -> "; curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8000$r"
done
```
Expected: all return `200` or `302` (no `500`).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor: migrate existing pages to shadcn-vue primitives"
```

---

## Task 5: Vora composed components

**Files:**
- Create: `app/resources/js/Components/vora/MetricCard.vue`
- Create: `app/resources/js/Components/vora/ChartCard.vue`
- Create: `app/resources/js/Components/vora/EmptyState.vue`
- Create: `app/resources/js/Components/vora/LoadingSkeleton.vue`
- Create: `app/resources/js/Components/vora/DataTable.vue`
- Create: `app/resources/js/Components/vora/PageHeader.vue`
- Create: `app/resources/js/Components/vora/ThemeToggle.vue`
- Create: `app/resources/js/Components/vora/UserMenu.vue`
- Create: `app/resources/js/composables/useTheme.js`

- [ ] **Step 1: Create `composables/useTheme.js`**

```js
import { ref } from 'vue';

const STORAGE_KEY = 'vora.theme';
const theme = ref('light');

function apply(value) {
    theme.value = value;
    document.documentElement.classList.toggle('dark', value === 'dark');
    localStorage.setItem(STORAGE_KEY, value);
}

export function initTheme() {
    const saved = localStorage.getItem(STORAGE_KEY);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    apply(saved || (prefersDark ? 'dark' : 'light'));
}

export function useTheme() {
    function toggle() {
        apply(theme.value === 'dark' ? 'light' : 'dark');
    }
    return { theme, toggle, setTheme: apply };
}
```

- [ ] **Step 2: Wire `initTheme()` into app boot**

In `app/resources/js/app.js`, add the import and call it before `createInertiaApp` (inside the `ensureAutoLogin().finally(() => { ... })` callback, as the first line):
```js
import { initTheme } from './composables/useTheme';
// ...inside the .finally callback, first line:
initTheme();
```

- [ ] **Step 3: Create `Components/vora/ThemeToggle.vue`**

```vue
<script setup>
import { Sun, Moon } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { useTheme } from '@/composables/useTheme';

const { theme, toggle } = useTheme();
</script>

<template>
    <Button variant="ghost" size="icon" @click="toggle" aria-label="Alternar tema">
        <Sun v-if="theme === 'dark'" class="h-4 w-4" />
        <Moon v-else class="h-4 w-4" />
    </Button>
</template>
```

- [ ] **Step 4: Create `Components/vora/MetricCard.vue`**

```vue
<script setup>
import { computed } from 'vue';
import { Card } from '@/Components/ui/card';
import { TrendingUp, TrendingDown } from 'lucide-vue-next';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    delta: { type: Number, default: null },
    icon: { type: [Object, Function], default: null },
});

const deltaPositive = computed(() => (props.delta ?? 0) >= 0);
</script>

<template>
    <Card class="p-5">
        <div class="flex items-start justify-between">
            <p class="text-[13px] font-medium text-muted-foreground">{{ label }}</p>
            <component :is="icon" v-if="icon" class="h-4 w-4 text-muted-foreground" />
        </div>
        <p class="mt-2 text-2xl font-semibold tracking-tight text-foreground tabular-nums">
            {{ value }}
        </p>
        <div v-if="delta !== null" class="mt-1 flex items-center gap-1 text-[12px] font-medium"
             :class="deltaPositive ? 'text-emerald-600' : 'text-destructive'">
            <component :is="deltaPositive ? TrendingUp : TrendingDown" class="h-3.5 w-3.5" />
            {{ Math.abs(delta) }}%
        </div>
    </Card>
</template>
```

- [ ] **Step 5: Create `Components/vora/ChartCard.vue`**

```vue
<script setup>
import { Card } from '@/Components/ui/card';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <Card class="p-5">
        <div class="mb-4 flex items-start justify-between">
            <div>
                <h3 class="text-[14px] font-semibold tracking-tight text-foreground">{{ title }}</h3>
                <p v-if="subtitle" class="text-[12px] text-muted-foreground">{{ subtitle }}</p>
            </div>
            <slot name="actions" />
        </div>
        <div class="min-h-[180px]">
            <slot />
        </div>
    </Card>
</template>
```

- [ ] **Step 6: Create `Components/vora/EmptyState.vue`**

```vue
<script setup>
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div v-if="icon" class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-muted">
            <component :is="icon" class="h-5 w-5 text-muted-foreground" />
        </div>
        <h3 class="text-[15px] font-semibold tracking-tight text-foreground">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-xs text-[13px] text-muted-foreground">{{ description }}</p>
        <div class="mt-4"><slot /></div>
    </div>
</template>
```

- [ ] **Step 7: Create `Components/vora/LoadingSkeleton.vue`**

```vue
<script setup>
import { Skeleton } from '@/Components/ui/skeleton';

defineProps({
    variant: { type: String, default: 'list' }, // 'list' | 'cards' | 'table'
    rows: { type: Number, default: 5 },
});
</script>

<template>
    <div v-if="variant === 'cards'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Skeleton v-for="i in 4" :key="i" class="h-28 rounded-[var(--radius)]" />
    </div>
    <div v-else-if="variant === 'table'" class="space-y-2">
        <Skeleton class="h-9 w-full" />
        <Skeleton v-for="i in rows" :key="i" class="h-12 w-full" />
    </div>
    <div v-else class="space-y-3">
        <div v-for="i in rows" :key="i" class="flex items-center gap-3">
            <Skeleton class="h-9 w-9 rounded-full" />
            <div class="flex-1 space-y-2">
                <Skeleton class="h-3.5 w-1/3" />
                <Skeleton class="h-3 w-2/3" />
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 8: Create `Components/vora/PageHeader.vue`**

```vue
<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex items-start justify-between gap-4 pb-5">
        <div>
            <h1 class="text-[20px] font-semibold tracking-tight text-foreground">{{ title }}</h1>
            <p v-if="description" class="mt-0.5 text-[13px] text-muted-foreground">{{ description }}</p>
        </div>
        <div class="flex items-center gap-2"><slot name="actions" /></div>
    </div>
</template>
```

- [ ] **Step 9: Create `Components/vora/DataTable.vue`**

```vue
<script setup>
import { ref, computed } from 'vue';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import { ChevronUp, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    // columns: [{ key, label, sortable?: bool, align?: 'left'|'right' }]
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    pageSize: { type: Number, default: 10 },
});

const sortKey = ref(null);
const sortDir = ref('asc');
const page = ref(1);

function toggleSort(col) {
    if (!col.sortable) return;
    if (sortKey.value === col.key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = col.key;
        sortDir.value = 'asc';
    }
}

const sorted = computed(() => {
    if (!sortKey.value) return props.rows;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...props.rows].sort((a, b) => {
        const av = a[sortKey.value], bv = b[sortKey.value];
        return av > bv ? dir : av < bv ? -dir : 0;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / props.pageSize)));
const paged = computed(() => {
    const start = (page.value - 1) * props.pageSize;
    return sorted.value.slice(start, start + props.pageSize);
});
</script>

<template>
    <div class="rounded-[var(--radius)] border border-border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead v-for="col in columns" :key="col.key"
                               :class="[col.align === 'right' ? 'text-right' : '', col.sortable ? 'cursor-pointer select-none' : '']"
                               @click="toggleSort(col)">
                        <span class="inline-flex items-center gap-1">
                            {{ col.label }}
                            <ChevronUp v-if="sortKey === col.key && sortDir === 'asc'" class="h-3.5 w-3.5" />
                            <ChevronDown v-else-if="sortKey === col.key && sortDir === 'desc'" class="h-3.5 w-3.5" />
                        </span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(row, i) in paged" :key="i">
                    <TableCell v-for="col in columns" :key="col.key"
                               :class="col.align === 'right' ? 'text-right tabular-nums' : ''">
                        <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                            {{ row[col.key] }}
                        </slot>
                    </TableCell>
                </TableRow>
                <TableRow v-if="!paged.length">
                    <TableCell :colspan="columns.length" class="py-10 text-center text-muted-foreground">
                        Nenhum registro
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
        <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-border px-3 py-2">
            <span class="text-[12px] text-muted-foreground">Página {{ page }} de {{ totalPages }}</span>
            <div class="flex gap-1">
                <Button variant="ghost" size="icon" :disabled="page === 1" @click="page--">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" :disabled="page === totalPages" @click="page++">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 10: Create `Components/vora/UserMenu.vue`**

```vue
<script setup>
import { computed } from 'vue';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { LogOut, User as UserIcon } from 'lucide-vue-next';
import { useAuth } from '@/Composables/useAuth';

const { user, logout } = useAuth();

const initials = computed(() => {
    const n = user.value?.name || 'Vora';
    return n.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button class="flex items-center gap-2 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <Avatar class="h-8 w-8">
                    <AvatarFallback>{{ initials }}</AvatarFallback>
                </Avatar>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuLabel>
                <div class="font-medium text-foreground">{{ user?.name || 'Usuário' }}</div>
                <div class="text-[12px] font-normal text-muted-foreground">{{ user?.email }}</div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem>
                <UserIcon class="mr-2 h-4 w-4" /> Perfil
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="logout">
                <LogOut class="mr-2 h-4 w-4" /> Sair
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
```

- [ ] **Step 11: Build**

Run (from `app/`): `npm run build`
Expected: `✓ built`, no errors.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: Vora composed components and theme system"
```

---

## Task 6: App shell

**Files:**
- Create: `app/resources/js/Layouts/Sidebar.vue`
- Create: `app/resources/js/Layouts/Header.vue`
- Modify: `app/resources/js/Layouts/AppLayout.vue` (full rewrite)

- [ ] **Step 1: Create `Layouts/Sidebar.vue`**

```vue
<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Tooltip, TooltipContent, TooltipProvider, TooltipTrigger,
} from '@/Components/ui/tooltip';
import {
    LayoutDashboard, MessagesSquare, Users, Building2, Settings,
    PanelLeftClose, PanelLeftOpen,
} from 'lucide-vue-next';

const collapsed = ref(true);
const hovered = ref(false);
const expanded = () => !collapsed.value || hovered.value;

const page = usePage();
const nav = [
    { label: 'Dashboard',  icon: LayoutDashboard, href: '/dashboard' },
    { label: 'Conversas',  icon: MessagesSquare,  href: '/conversations' },
    { label: 'Setores',    icon: Building2,       href: '/sectors' },
    { label: 'Usuários',   icon: Users,           href: '/users' },
    { label: 'Configurações', icon: Settings,     href: '/settings' },
];

function isActive(href) {
    return page.url.startsWith(href);
}
</script>

<template>
    <aside
        class="flex h-screen flex-col bg-sidebar text-sidebar-foreground transition-[width] duration-200 ease-out"
        :class="expanded() ? 'w-60' : 'w-[64px]'"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
    >
        <!-- Wordmark -->
        <div class="flex h-14 items-center px-4">
            <span class="text-[17px] font-semibold tracking-tight text-white">
                <template v-if="expanded()">Vora<span class="text-vora-orange">.</span></template>
                <template v-else>V<span class="text-vora-orange">.</span></template>
            </span>
        </div>

        <!-- Nav -->
        <TooltipProvider :delay-duration="0">
            <nav class="flex-1 space-y-1 px-2 py-2">
                <Tooltip v-for="item in nav" :key="item.href">
                    <TooltipTrigger as-child>
                        <Link
                            :href="item.href"
                            class="flex items-center gap-3 rounded-[var(--radius)] px-3 py-2 text-[13px] font-medium transition-colors"
                            :class="isActive(item.href)
                                ? 'bg-sidebar-active text-white'
                                : 'text-sidebar-foreground hover:bg-sidebar-accent'"
                        >
                            <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" />
                            <span v-if="expanded()" class="truncate">{{ item.label }}</span>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent v-if="!expanded()" side="right">{{ item.label }}</TooltipContent>
                </Tooltip>
            </nav>
        </TooltipProvider>

        <!-- Collapse toggle -->
        <div class="border-t border-sidebar-border p-2">
            <button
                class="flex w-full items-center gap-3 rounded-[var(--radius)] px-3 py-2 text-[13px] text-sidebar-muted transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground"
                @click="collapsed = !collapsed"
            >
                <component :is="collapsed ? PanelLeftOpen : PanelLeftClose" class="h-[18px] w-[18px] shrink-0" />
                <span v-if="expanded()">Recolher</span>
            </button>
        </div>
    </aside>
</template>
```

- [ ] **Step 2: Add `sidebar` color utilities to `tailwind.config.js`**

The `sidebar.*` raw tokens were added in Task 2. Confirm `bg-sidebar`, `text-sidebar-foreground`, `bg-sidebar-accent`, `bg-sidebar-active`, `border-sidebar-border`, `text-sidebar-muted` all resolve — they do, because `sidebar` is a nested color object. No change needed; this step is a verification checkpoint only.

- [ ] **Step 3: Create `Layouts/Header.vue`**

```vue
<script setup>
import { Search, Bell } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import ThemeToggle from '@/Components/vora/ThemeToggle.vue';
import UserMenu from '@/Components/vora/UserMenu.vue';

defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <header class="flex h-14 shrink-0 items-center justify-between border-b border-border bg-card px-5">
        <div class="flex items-center gap-3">
            <h2 v-if="title" class="text-[14px] font-semibold tracking-tight text-foreground">{{ title }}</h2>
        </div>
        <div class="flex items-center gap-1">
            <Button variant="ghost" size="icon" aria-label="Buscar">
                <Search class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" aria-label="Notificações">
                <Bell class="h-4 w-4" />
            </Button>
            <ThemeToggle />
            <div class="mx-1 h-5 w-px bg-border" />
            <UserMenu />
        </div>
    </header>
</template>
```

- [ ] **Step 4: Rewrite `Layouts/AppLayout.vue`**

Full replacement:
```vue
<script setup>
import Sidebar from '@/Layouts/Sidebar.vue';
import Header from '@/Layouts/Header.vue';
import { Toaster } from '@/Components/ui/sonner';

defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-background">
        <Sidebar />
        <div class="flex min-w-0 flex-1 flex-col">
            <Header :title="title" />
            <main class="min-h-0 flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
        <Toaster rich-colors position="top-right" />
    </div>
</template>
```

- [ ] **Step 5: Delete the superseded old shell components**

The old `resources/js/Components/Sidebar.vue` and `resources/js/Components/Topbar.vue` are replaced by `Layouts/Sidebar.vue` and `Layouts/Header.vue`. Confirm nothing imports them:
```bash
grep -rn "Components/Sidebar\|Components/Topbar" resources/js --include=*.vue
```
If the only references were inside the old `AppLayout.vue` (now rewritten), delete both files:
```bash
rm resources/js/Components/Sidebar.vue resources/js/Components/Topbar.vue
```
If anything else imports them, update those imports to the new `Layouts/` paths first.

- [ ] **Step 6: Build**

Run (from `app/`): `npm run build`
Expected: `✓ built`, no errors.

- [ ] **Step 7: Smoke-check routes (server must be running on :8000)**

```bash
for r in /conversations /dashboard /sectors /users /settings; do
  echo -n "$r -> "; curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8000$r"
done
```
Expected: all `200`/`302`, no `500`.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: Vora app shell (collapsible navy sidebar, header, layout)"
```

---

## Task 7: Component showcase route (`/_ui`)

**Files:**
- Create: `app/resources/js/Pages/_Ui/Index.vue`
- Modify: `app/routes/web.php`

- [ ] **Step 1: Add the dev-only route to `routes/web.php`**

Append before the closing of the file:
```php
if (app()->environment('local')) {
    Route::get('/_ui', fn () => Inertia::render('_Ui/Index'))->name('_ui');
}
```

- [ ] **Step 2: Create `Pages/_Ui/Index.vue`**

```vue
<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Card } from '@/Components/ui/card';
import MetricCard from '@/Components/vora/MetricCard.vue';
import ChartCard from '@/Components/vora/ChartCard.vue';
import EmptyState from '@/Components/vora/EmptyState.vue';
import LoadingSkeleton from '@/Components/vora/LoadingSkeleton.vue';
import DataTable from '@/Components/vora/DataTable.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { MessagesSquare, Activity } from 'lucide-vue-next';

const tableColumns = [
    { key: 'name', label: 'Nome', sortable: true },
    { key: 'tickets', label: 'Tickets', sortable: true, align: 'right' },
];
const tableRows = [
    { name: 'Ana', tickets: 12 },
    { name: 'Bruno', tickets: 7 },
    { name: 'Carla', tickets: 19 },
];
</script>

<template>
    <Head title="UI Showcase" />
    <AppLayout title="UI Showcase">
        <div class="space-y-10 p-8">
            <PageHeader title="Showcase de componentes" description="Todos os componentes do design system Vora — claro e escuro." />

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">Buttons</h3>
                <div class="flex flex-wrap gap-2">
                    <Button>Default</Button>
                    <Button variant="secondary">Secondary</Button>
                    <Button variant="outline">Outline</Button>
                    <Button variant="ghost">Ghost</Button>
                    <Button variant="destructive">Destructive</Button>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">Inputs & Badges</h3>
                <div class="flex flex-wrap items-center gap-3">
                    <Input class="w-64" placeholder="Digite algo…" />
                    <Badge>Default</Badge>
                    <Badge variant="secondary">Secondary</Badge>
                    <Badge variant="outline">Outline</Badge>
                    <Badge variant="destructive">Destructive</Badge>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">Metric cards</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard label="Tickets abertos" :value="42" :delta="8" :icon="MessagesSquare" />
                    <MetricCard label="TMA (min)" :value="6.4" :delta="-3" :icon="Activity" />
                    <MetricCard label="Na fila" :value="5" />
                    <MetricCard label="Resolvidos hoje" :value="128" :delta="12" />
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ChartCard title="Volume 14 dias" subtitle="Mensagens recebidas">
                    <div class="flex h-full items-center justify-center text-muted-foreground">[gráfico]</div>
                </ChartCard>
                <Card class="p-5">
                    <EmptyState :icon="MessagesSquare" title="Nenhuma conversa"
                                description="Quando chegar uma mensagem ela aparece aqui." />
                </Card>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">DataTable</h3>
                <DataTable :columns="tableColumns" :rows="tableRows" :page-size="2" />
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">Loading skeletons</h3>
                <LoadingSkeleton variant="cards" />
                <LoadingSkeleton variant="list" :rows="3" />
            </section>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 3: Build**

Run (from `app/`): `npm run build`
Expected: `✓ built`, no errors.

- [ ] **Step 4: Verify the showcase renders (server on :8000)**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/_ui
```
Expected: `200`.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: dev-only /_ui component showcase route"
```

---

## Task 8: Final verification

- [ ] **Step 1: Clean build**

Run (from `app/`): `npm run build`
Expected: `✓ built`, zero errors/warnings about missing modules.

- [ ] **Step 2: Lint**

Run (from `app/`): `npm run lint`
Fix any errors introduced by the new files. Pre-existing warnings unrelated to this work may be left.

- [ ] **Step 3: Full route smoke test (server on :8000)**

```bash
for r in / /conversations /dashboard /sectors /users /settings /_ui; do
  echo -n "$r -> "; curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8000$r"
done
```
Expected: every route returns `200` or `302`. No `500`.

- [ ] **Step 4: Manual checklist (note results in the final report)**

- Sidebar collapses/expands; tooltips show when collapsed.
- Theme toggle flips light/dark and persists across reload.
- `/_ui` shows every component correctly in both themes.
- No console errors on `/conversations` and `/dashboard`.

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: SP1 Vora design system complete"
```

---

## Self-Review

**Spec coverage:**
- Tokens light+dark → Task 2 ✓
- shadcn-vue setup → Task 1 + Task 3 ✓
- ~20 primitives → Task 3 (24 components requested) ✓
- ~8 composed components → Task 5 (MetricCard, ChartCard, EmptyState, LoadingSkeleton, DataTable, PageHeader, UserMenu, ThemeToggle) ✓
- App shell (Sidebar/Header/AppLayout) → Task 6 ✓
- Migration/compile-fix → Task 4 ✓
- `/_ui` showcase → Task 7 ✓
- Verification → Task 8 ✓
- Git baseline → Task 0 ✓

**Placeholder scan:** No TBD/TODO. Task 4 is inherently iterative ("build until clean") but gives the exact mapping table and files — acceptable, not a placeholder.

**Type consistency:** `useTheme()` returns `{ theme, toggle, setTheme }` — consumed in ThemeToggle (`theme`, `toggle`) ✓. `initTheme()` standalone export — consumed in app.js ✓. `useAuth()` returns `{ user, logout }` used in UserMenu — matches existing `useAuth.js` ✓. DataTable column shape `{ key, label, sortable, align }` consistent between definition and showcase usage ✓.

**Note on existing deps:** `class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-vue-next`, `tailwindcss-animate` and `cn()` already exist — Task 1 only adds `reka-ui` + `@vueuse/core`.
