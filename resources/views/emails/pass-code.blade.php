<x-mail::message>
# Your {{ $season->pass_name }} {{ $season->pass_year }} Promo Code

Hello {{ $passRequest->passholder_first_name }},

Your promo code for the {{ $season->pass_name }}  is ready!

**Your Promo Code:** `{{ $passRequest->promo_code }}`

## Passholder Details
- **Name:** {{ $passRequest->passholder_first_name }} {{ $passRequest->passholder_last_name }}
- **Email:** {{ $passRequest->passholder_email }}
- **Pass Type:** {{ $passRequest->pass_type }}

This promo code MUST be used by the passholder above. If you need a code for a different person, then please submit a new request for that person.

@if($passRequest->is_renewal)
## Renewal Information
- **Pass ID:** {{ $passRequest->renewal_pass_id }}
@if($passRequest->renewal_order_number)
- **Order Number:** {{ $passRequest->renewal_order_number }}
@endif
@endif

# Redemption instructions

1. Visit the {{ $season->pass_name }} website
2. Add the pass (or passes) to your cart
3. Apply the promo code(s) for each pass to your cart, you need to apply a unique promo code for each pass

Remember, this code cannot be combined with other discounts, and *please* keep the details of this program confidential. 
You can provide friends and family with the information to create their own account and request their own promo code, or request promo codes on their behalf with their permission, but don't post this deal on social media or anywhere public.

Thanks for participating and please, think snow!

<x-mail::button :url="config('app.url')">
View Dashboard
</x-mail::button>

Thanks,<br>
Ben
</x-mail::message>
