<script setup>
defineProps({
    room: { type: String, default: 'Boardroom' },
    time: { type: String, default: '09:48' },
    large: { type: Boolean, default: false },
});
</script>

<template>
    <!-- A room display as it looks on the wall: the product in one picture. -->
    <div class="rounded-[18px] bg-navy-950 p-2.5 shadow-[0_24px_60px_rgba(0,0,0,0.35)]">
        <div class="relative flex aspect-[4/3] flex-col justify-between overflow-hidden rounded-lg bg-gray-900 text-slate-50" :class="large ? 'px-8 py-7' : 'px-6 py-5'">
            <div class="flex justify-between font-semibold" :class="large ? 'text-sm' : 'text-xs'">
                <span>{{ room }}</span>
                <span class="tabular-nums">{{ time }}</span>
            </div>
            <div class="flex flex-col gap-1.5">
                <span class="inline-flex items-center gap-2 font-semibold text-green-400" :class="large ? 'text-[13px]' : 'text-[11px]'">
                    <span class="h-2.5 w-2.5 rounded-full bg-green-400"></span>Available
                </span>
                <span class="font-bold leading-none tracking-tight" :class="large ? 'text-[40px]' : 'text-[32px]'">All yours!</span>
                <span class="text-slate-300" :class="large ? 'text-base' : 'text-[13px]'">Free until 11:00</span>
            </div>
            <div class="flex gap-1.5">
                <span class="rounded-full border border-green-400 px-2.5 py-1 text-[11px] font-semibold text-green-400">15 min</span>
                <span class="rounded-full border border-green-400 px-2.5 py-1 text-[11px] font-semibold text-green-400">30 min</span>
                <span class="rounded-full bg-green-400 px-2.5 py-1 text-[11px] font-semibold text-gray-900">Book now</span>
            </div>
            <div class="absolute inset-x-0 bottom-0 h-1.5 bg-green-400"></div>
        </div>
    </div>
</template>
