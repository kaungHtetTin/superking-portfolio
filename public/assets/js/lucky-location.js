(() => {
    const form = document.getElementById('lucky-reveal');
    if (!form) return;
    const button = form.querySelector('button');
    const status = document.getElementById('lucky-location-status');
    const label = button.textContent;
    const my = document.documentElement.lang === 'my';
    const message = (en, mm) => my ? mm : en;
    let busy = false, request = 0;
    const clear = () => {
        for (const key of ['latitude','longitude','accuracy_m','location_consent']) form.elements[key].value = '';
    };
    const fail = text => {
        busy = false;
        button.disabled = false;
        button.textContent = label;
        status.textContent = text;
        form.removeAttribute('aria-busy');
        clear();
    };
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (busy) return;
        clear();
        if (!window.isSecureContext) {
            fail(message('Location requires a secure HTTPS connection. Open the promotion on the official HTTPS website, then try again. Your code is still unused.', 'တည်နေရာရယူရန် လုံခြုံသော HTTPS ဝဘ်ဆိုက် လိုအပ်ပါသည်။ တရားဝင် HTTPS ဝဘ်ဆိုက်တွင် ဖွင့်ပြီး ထပ်ကြိုးစားပါ။ သင့်ကုဒ်ကို မသုံးရသေးပါ။'));
            return;
        }
        if (!navigator.geolocation) {
            fail(message('This browser does not support location. Try a browser with location services enabled. Your code is still unused.', 'ဤ browser တွင် တည်နေရာ မရယူနိုင်ပါ။ တည်နေရာဝန်ဆောင်မှုရသော browser ဖြင့် ကြိုးစားပါ။ သင့်ကုဒ်ကို မသုံးရသေးပါ။'));
            return;
        }
        busy = true;
        button.disabled = true;
        button.textContent = message('Getting location…', 'တည်နေရာရယူနေသည်…');
        form.setAttribute('aria-busy','true');
        status.textContent = message('Please allow location access when your browser asks.', 'Browser က တောင်းဆိုသည့်အခါ တည်နေရာအသုံးပြုခွင့်ကို ခွင့်ပြုပေးပါ။');
        const current = ++request;
        const timer = setTimeout(() => {
            if (current !== request || !busy) return;
            request++;
            fail(message('Location took too long. Turn on location services and tap Reveal prize to retry. Your code is still unused.', 'တည်နေရာရယူချိန် ကြာလွန်းနေပါသည်။ တည်နေရာဝန်ဆောင်မှုကို ဖွင့်ပြီး ထပ်မံကြိုးစားပါ။ သင့်ကုဒ်ကို မသုံးရသေးပါ။'));
        }, 25000);
        const error = reason => {
            if (current !== request || !busy) return;
            clearTimeout(timer);
            if (reason.code === 1) {
                fail(message('Location permission was denied. Allow location in your browser’s site settings, then tap Reveal prize again. Your code is still unused.', 'တည်နေရာအသုံးပြုခွင့် မရရှိပါ။ Browser ၏ ဝဘ်ဆိုက်ဆက်တင်တွင် တည်နေရာကို ခွင့်ပြုပြီး ထပ်မံကြိုးစားပါ။ သင့်ကုဒ်ကို မသုံးရသေးပါ။'));
            } else {
                fail(message('Could not get your location. Turn on location services, move to an area with a better signal, and try again. Your code is still unused.', 'တည်နေရာကို မရရှိပါ။ တည်နေရာဝန်ဆောင်မှုကို ဖွင့်ပြီး အချက်ပြကောင်းသောနေရာတွင် ထပ်မံကြိုးစားပါ။ သင့်ကုဒ်ကို မသုံးရသေးပါ။'));
            }
        };
        try {
            navigator.geolocation.getCurrentPosition(position => {
                if (current !== request || !busy) return;
                const { latitude, longitude, accuracy } = position.coords;
                if (![latitude, longitude, accuracy].every(Number.isFinite) || Math.abs(latitude) > 90 || Math.abs(longitude) > 180 || accuracy < 0 || accuracy > 10000000) {
                    error({code:2}); return;
                }
                clearTimeout(timer);
                form.elements.latitude.value = latitude;
                form.elements.longitude.value = longitude;
                form.elements.accuracy_m.value = accuracy;
                form.elements.location_consent.value = 'reveal-location-v1';
                button.textContent = message('Revealing…', 'ဆုရလဒ်ဖွင့်နေသည်…');
                status.textContent = message('Location received. Opening your result…', 'တည်နေရာရရှိပါပြီ။ ရလဒ်ဖွင့်နေသည်…');
                HTMLFormElement.prototype.submit.call(form);
            }, error, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        } catch (_) { error({code:2}); }
    });
    window.addEventListener('pageshow', () => {
        request++;
        busy = false;
        button.disabled = false;
        button.textContent = label;
        status.textContent = '';
        form.removeAttribute('aria-busy');
        clear();
    });
})();
