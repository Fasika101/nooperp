<script>
    window.libaPlayTelegramNotifySound = function () {
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) {
                return;
            }
            var ctx = new AC();
            var o = ctx.createOscillator();
            var g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(880, ctx.currentTime);
            g.gain.setValueAtTime(0.06, ctx.currentTime);
            o.connect(g);
            g.connect(ctx.destination);
            o.start();
            setTimeout(function () {
                try {
                    o.stop();
                    ctx.close && ctx.close();
                } catch (e) {}
            }, 160);
        } catch (e) {}
    };
</script>
@livewire(\App\Livewire\Filament\TelegramUnreadPoller::class)
