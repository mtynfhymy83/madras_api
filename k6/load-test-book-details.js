import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://api-dev.madras.app';

// متریک‌ها
const under100ms = new Rate('response_under_100ms');
const bookDetailDuration = new Trend('book_detail_duration');

export const options = {
  stages: [
    { duration: '30s', target: 20 },
    { duration: '1m', target: 50 },
    { duration: '2m', target: 100 },
    { duration: '2m', target: 100 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    // کمی واقع‌بینانه‌تر برای تست از راه دور (Network Latency را لحاظ کن)
    'http_req_duration': ['p(95)<200'], 
    'response_under_100ms': ['rate>0.9'],
    'http_req_failed': ['rate<0.01'],
  },
};

export default function () {
  // تولید ID تصادفی بین 1 تا 1000 برای جلوگیری از کش شدن همه درخواست‌ها
  // فرض بر این است که دیتابیس سید (Seed) شده و این کتاب‌ها موجودند
  const id = Math.floor(Math.random() * 20) + 1;
  
  const url = `${BASE_URL}/api/v1/books/${id}`;

  const res = http.get(url, {
    tags: { name: 'book_detail' },
    timeout: '5s', // تایم‌اوت 30 ثانیه برای API خیلی زیاد است، زودتر Fail شود بهتر است
  });

  // چک کردن: فقط 200 قابل قبول است (مگر اینکه مطمئن باشیم برخی IDها نیستند)
  const ok = check(res, {
    'status is 200': (r) => r.status === 200,
    'content present': (r) => r.body && r.body.length > 0, // اطمینان از اینکه بادی خالی نیست
  });

  const duration = res.timings.duration; // خود k6 هندل می‌کند نیاز به || 0 نیست
  bookDetailDuration.add(duration);
  
  // متریک سفارشی: آیا زیر 100 میلی‌ثانیه بود؟
  under100ms.add(duration < 100);

  if (!ok) {
    // لاگ کردن خطاها برای دیباگ (فقط وقتی تست فیل شد)
    console.warn(`Error: [${res.status}] ID:${id} - Time:${duration.toFixed(0)}ms`);
  }

  // وقفه منطقی‌تر (کاربر واقعی)
  // اگر هدف فشار حداکثری است، همان اعداد خودت را برگردان
  sleep(Math.random() * 1 + 0.5); // بین 0.5 تا 1.5 ثانیه
}

// تابع handleSummary شما عالی بود، تغییری ندادم
export function handleSummary(data) {
  return {
    'k6/summary.json': JSON.stringify(data, null, 2),
    'k6/summary.html': htmlReport(data),
  };
}

function htmlReport(data) {
    // ... (همان کد خودت)
    // فقط یک نکته ریز: در کد خودت p95 را بر 1000 تقسیم کردی (ثانیه)
    // حواست باشد در نمایش HTML دوباره ضربدر 1000 کنی که درست نشان دهد.
    const m = data.metrics || {};
    const p95 = m.http_req_duration ? m.http_req_duration.values['p(95)'] : 0;
    const under = m.response_under_100ms ? m.response_under_100ms.values.rate : 0;
    const failed = m.http_req_failed ? m.http_req_failed.values.rate : 0;
    
    // شرط پاس شدن
    const passed = p95 < 100 && under >= 0.9 && failed < 0.01;

    return `
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Test Report</title></head>
    <body style="font-family: sans-serif; padding: 2rem;">
      <h1>نتایج تست فشار</h1>
      <ul>
        <li>P95 Duration: ${p95.toFixed(2)} ms ${p95 < 100 ? '✅' : '❌'}</li>
        <li>Under 100ms: ${(under * 100).toFixed(1)}% ${under >= 0.9 ? '✅' : '❌'}</li>
        <li>Errors: ${(failed * 100).toFixed(2)}%</li>
      </ul>
      <h2>${passed ? 'PASSED ✅' : 'FAILED ❌'}</h2>
    </body></html>`;
}