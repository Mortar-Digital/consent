// Stand-in "tracker" for the e2e suite: logs that it ran and sets cookies
// owned by the analytics category.
window.mdTestLog = (window.mdTestLog || []).concat('external:' + (typeof window.mdTestBefore));
document.cookie = '_sdtest_a=1; Path=/';
document.cookie = '_sdtest_b=1; Path=/';
