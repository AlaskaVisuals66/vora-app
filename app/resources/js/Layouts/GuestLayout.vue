<template>
    <div class="relative min-h-screen flex items-center justify-center px-4 overflow-hidden bg-[#0A0A0A]">
        <!-- Subtle grid + soft vignette — premium without being loud -->
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(255,255,255,0.06),transparent_60%)]"></div>
        <div class="absolute inset-0 opacity-[0.04]"
             style="background-image: linear-gradient(rgba(255,255,255,.4) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.4) 1px, transparent 1px); background-size: 64px 64px;"></div>

        <div class="relative w-full max-w-md z-10">
            <slot />
        </div>
    </div>
</template>
