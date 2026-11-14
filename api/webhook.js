const crypto = require('crypto');

const STRIPE_WEBHOOK_SECRET = process.env.STRIPE_WEBHOOK_SECRET || 'whsec_JgTTr8BUaeLyLLiV24aom8SOrdVlBfuL';

module.exports = async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Stripe-Signature');

  if (req.method === 'OPTIONS') return res.status(204).end();

  if (req.method === 'GET') {
    return res.status(200).json({ status: 'active', gateway: 'stripe' });
  }

  try {
    const sig = req.headers['stripe-signature'];
    const event = req.body;

    // Log the event type
    console.log('[webhook] Received Stripe event:', event?.type || event?.id);

    return res.status(200).json({ received: true });
  } catch (err) {
    console.error('[webhook] Error processing Stripe webhook:', err);
    return res.status(200).json({ received: true });
  }
};
