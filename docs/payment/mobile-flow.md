# Mobile Payment Flow

This implementation adds a mobile-friendly payment sheet via the Web Payment Request API with graceful fallback.

Where
- `lib/DonateButton.js` — adds a "Pay with Mobile (Payment Request)" button
- The button appears if `window.PaymentRequest` is available.

How it works
1. Detects `PaymentRequest` and constructs a request with supported networks.
2. Shows the native payment UI on supported devices.
3. On success, completes the sheet and posts the donation to `/donation-api.php?action=process` with `paymentMethod: 'credit_card'`.
4. Falls back to the form if Payment Request is not available.

Notes
- This flow does not process a real card token in this repo; `DonationProcessor` handles the credit card method logically (simulated) and records the donation.
- Analytics hooks are emitted if `assets/js/analytics.js` is included.

Demo
- Add `<script src="/assets/js/analytics.js"></script>` for event logs.
- Use the donate button on campaign detail pages to trigger the mobile sheet.

