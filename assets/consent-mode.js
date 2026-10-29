// Google Consent Mode v2 defaults. Not enqueued: ConsentModeSnippet inlines
// this function expression first thing in <head> and calls it with the
// signal map, so it must stay ES5, tiny and free of a trailing semicolon.
(function (c) {
	var w = window, granted = [], m, v, now = Date.now() / 1000, state = {};
	w.dataLayer = w.dataLayer || [];
	w.gtag = w.gtag || function () { w.dataLayer.push(arguments); };
	m = document.cookie.match(new RegExp('(?:^|;\\s*)' + c.cookie + '=([^;]*)'));
	if (m) {
		v = new RegExp(c.pattern).exec(m[1]);
		if (v && +v[1] === c.policy && +v[2] <= now + 300 && now - +v[2] <= c.lifetime && v[4]) { granted = v[4].split('.'); }
	}
	for (var signal in c.signals) {
		state[signal] = c.signals[signal].length && c.signals[signal].every(function (id) {
			return c.required.indexOf(id) > -1 || granted.indexOf(id) > -1;
		}) ? 'granted' : 'denied';
	}
	state.wait_for_update = 500;
	w.gtag('consent', 'default', state);
	w.gtag('set', 'ads_data_redaction', true);
})
