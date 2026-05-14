<template>
    <div class="relative min-h-screen flex items-center justify-center px-4 overflow-hidden bg-[#00153D]">
        <!-- Subtle grid + radial accent — premium without being loud -->
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_75%_25%,rgba(255,90,31,0.18),transparent_55%)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_85%,rgba(255,59,48,0.12),transparent_50%)]"></div>
        <div class="absolute inset-0 opacity-[0.04]"
             style="background-image: linear-gradient(rgba(255,255,255,.4) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.4) 1px, transparent 1px); background-size: 64px 64px;"></div>

        <div class="relative w-full max-w-md z-10">
            <slot />
        </div>
    </div>
</template>
