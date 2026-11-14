const STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY ||
  Buffer.from('cmtfbGl2ZV81MVVEZzVPR2NZeEp2Q3ExOVl4a1RKbWVUck5FOGxZcEMxSHVRdkZZMzZScUd2VVMzRWFhREJrbm5WUWxveGZKcTFQNDVpa1gyeGp0ZERwS0s4TVdaV2lQdDAwbWdxNU1ISWI=', 'base64').toString('utf8');

module.exports = async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') return res.status(204).end();
  if (req.method !== 'POST') return res.status(405).json({ success: false, message: 'Method not allowed' });

  if (!STRIPE_SECRET_KEY) {
    console.error('[checkout] Missing Stripe credentials');
    return res.status(500).json({ success: false, message: 'Server configuration error.' });
  }

  const {
    amount,
    phone,
    email,
    name,
    monthly = false,
    currency = 'usd',
    return_url,
    description
  } = req.body || {};

  if (!amount || isNaN(parseFloat(amount)) || parseFloat(amount) <= 0) {
    return res.status(400).json({ success: false, message: 'Invalid donation amount.' });
  }

  const cleanAmount = parseFloat(parseFloat(amount).toFixed(2));
  const unitAmount = Math.round(cleanAmount * 100);
  const cleanCurrency = String(currency || 'usd').toLowerCase();
  const isMonthly = Boolean(monthly && monthly !== 'false' && monthly !== '0');

  const origin = req.headers['origin'] || (req.headers['referer'] ? new URL(req.headers['referer']).origin : 'https://donate.kylerescuer.org');
  const successUrl = return_url || `${origin}/thank-you/?session_id={CHECKOUT_SESSION_ID}&amount=${cleanAmount}`;
  const cancelUrl = `${origin}/`;

  const params = new URLSearchParams();
  params.append('mode', isMonthly ? 'subscription' : 'payment');
  if (!isMonthly) {
    params.append('submit_type', 'donate');
  }
  params.append('line_items[0][price_data][currency]', cleanCurrency);
  params.append('line_items[0][price_data][product_data][name]', description || 'Donation');
  params.append('line_items[0][price_data][unit_amount]', String(unitAmount));
  params.append('line_items[0][quantity]', '1');

  if (isMonthly) {
    params.append('line_items[0][price_data][recurring][interval]', 'month');
  }

  if (email && String(email).includes('@')) {
    params.append('customer_email', String(email).trim());
  }

  if (name) {
    params.append('metadata[donor_name]', String(name).slice(0, 100));
  }
  if (phone) {
    params.append('metadata[phone]', String(phone).slice(0, 50));
  }
  params.append('metadata[source]', 'kylerescuer');

  params.append('success_url', successUrl);
  params.append('cancel_url', cancelUrl);

  try {
    const stripeRes = await fetch('https://api.stripe.com/v1/checkout/sessions', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${STRIPE_SECRET_KEY}`,
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: params.toString()
    });

    const data = await stripeRes.json();

    if (!stripeRes.ok) {
      const msg = data.error?.message || 'Failed to process payment.';
      console.error('[checkout] Stripe error:', stripeRes.status, msg);
      return res.status(200).json({ success: false, message: msg });
    }

    return res.status(200).json({
      success: true,
      sessionId: data.id,
      redirectUrl: data.url,
      url: data.url
    });
  } catch (err) {
    console.error('[checkout] fetch error:', err);
    return res.status(500).json({ success: false, message: 'Communication error with payment processor.' });
  }
};
