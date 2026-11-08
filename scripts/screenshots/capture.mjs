// Captures the screenshots in docs/screenshots, against the running demo.
//
// Needs Chromium in the PHP container, which the image does not ship:
//
//   docker exec -u root evd-php apk add --no-cache chromium font-noto
//   docker exec -w /var/www/html/scripts/screenshots evd-php node capture.mjs
//
// It signs in as Chloe, records views in the Lab and starts a Console run,
// so run it on demo data, not on data you want to keep as it is.
import { execSync } from 'node:child_process';
import { open, shot, evaluate, sleep, close } from './cdp.mjs';

const live = () => execSync(`php /var/www/html/artisan tinker --execute 'app(App\\Demo\\TrafficSimulator::class)->liveReaders(36);'`, { stdio: 'ignore' });
// A form post from the page. `follow: false` leaves the redirect alone, so a flashed message survives for the next page.
const post = (path, body, follow = true) => evaluate(`(async () => {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const response = await fetch(${JSON.stringify(path)}, { method: 'POST', redirect: ${follow ? "'follow'" : "'manual'"}, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + encodeURIComponent(token) + '&' + ${JSON.stringify(body)} });
    return response.url;
})()`);


live();

// An article, arrived at from the newsletter: the receipt shows the dimensions.
await open('/posts/queues-in-laravel-from-first-job-to-horizon?utm_source=nl&utm_medium=email&utm_campaign=weekly-digest', 3500);
await shot('article', { maxHeight: 1500 });

// The Recording lab after a handful of attempts.
await open('/lab');
await post('/lab/visitor', '');
const target = 'target=post:indexes-explained-with-a-phone-book';
for (const attempt of [
    'agent=chrome&ip=203.0.113.10&arrival=newsletter&country=NL&cooldown=5',
    'agent=chrome&ip=203.0.113.10&arrival=newsletter&country=NL&cooldown=5',
    'agent=googlebot&ip=203.0.113.10&arrival=google&cooldown=0',
    'agent=iphone&ip=198.51.100.7&arrival=hn&cooldown=0',
    'agent=android&ip=203.0.113.10&arrival=chatgpt&country=JP&cooldown=0&viewer=4',
    'agent=ipad&ip=2001:db8::1&arrival=reddit&country=BR&cooldown=0&viewer=3&queue=1',
]) {
    await post('/lab', `${target}&${attempt}`);
}
await post('/lab/burst', '', false);
await open('/lab', 1500);
await shot('lab', { maxHeight: 2300 });

// The Console with a finished run.
await open('/console');
let runId = null;
for (let attempt = 0; attempt < 30 && ! runId; attempt++) {
    // The Console refuses a second run while one is active and shows that one instead.
    const id = new URL(await post('/console/run', 'command=views:rollup&options%5B--dry-run%5D=1')).searchParams.get('run');
    const status = await evaluate(`fetch('/console/runs/${id}', { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((r) => r.command)`);
    if (status.startsWith('views:rollup')) { runId = id; } else { await sleep(5000); }
}
for (let i = 0; i < 120; i++) {
    const status = await evaluate(`fetch('/console/runs/${runId}', { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((r) => r.status)`);
    if (status === 'finished' || status === 'stopped') break;
    await sleep(2000);
}
await open(`/console?run=${runId}`, 2500);
await shot('console', { maxHeight: 2400 });

// Stats.
await open('/stats/30d', 2500);
await shot('stats', { maxHeight: 2600 });
await open('/stats/30d', 1500);
const curves = await evaluate(`document.getElementById('curves').getBoundingClientRect().top + window.scrollY - 24`);
await shot('stats-trending', { from: curves, maxHeight: 610 });
await open('/posts/queues-in-laravel-from-first-job-to-horizon/stats/1y', 2500);
await shot('post-stats', { maxHeight: 2300 });

// Lists and the tour.
await open('/posts?sort=week');
await shot('articles', { maxHeight: 1300 });
await open('/episodes/counting-views-at-scale');
await shot('episode', { maxHeight: 1200 });

// Signed in as a reader with history.
await open('/tour');
await post('/demo/login/3', '');
await sleep(25000); // the queue works out her recommendations
live();
await open('/', 2500);
await shot('home', { maxHeight: 2000 });
await open('/account', 2500);
await shot('account', { maxHeight: 1700 });

close();
