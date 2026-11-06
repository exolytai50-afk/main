document.addEventListener('alpine:init', () => {
    Alpine.data('stepTimer', (minutes) => ({
        duration: Math.round((minutes || 1) * 60),
        elapsed: 0,
        running: false,
        _interval: null,
        
        get progress() {
            if (this.duration <= 0) return 0;
            return Math.min(100, (this.elapsed / this.duration) * 100);
        },
        
        get display() {
            const mins = Math.floor(this.elapsed / 60);
            const secs = this.elapsed % 60;
            return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        },
        
        toggle() {
            if (this.running) {
                clearInterval(this._interval);
                this.running = false;
            } else {
                this.running = true;
                this._interval = setInterval(() => {
                    if (this.elapsed < this.duration) {
                        this.elapsed++;
                    } else {
                        clearInterval(this._interval);
                        this.running = false;
                    }
                }, 1000);
            }
        },
        
        reset() {
            clearInterval(this._interval);
            this.elapsed = 0;
            this.running = false;
        }
    }));
});