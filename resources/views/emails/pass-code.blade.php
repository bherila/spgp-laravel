<x-mail::message>
# Your {{ $season->pass_name }} {{ $season->pass_year }} Promo Code

Hello {{ $passRequest->passholder_first_name }},

Your promo code for the {{ $season->pass_name }} {{ $season->pass_year }} is ready!

**Your Promo Code:** `{{ $passRequest->promo_code }}`

## Passholder Details
- **Name:** {{ $passRequest->passholder_first_name }} {{ $passRequest->passholder_last_name }}
- **Email:** {{ $passRequest->passholder_email }}
- **Pass Type:** {{ $passRequest->pass_type }}

@if($passRequest->is_renewal)
## Renewal Information
- **Pass ID:** {{ $passRequest->renewal_pass_id }}
@if($passRequest->renewal_order_number)
- **Order Number:** {{ $passRequest->renewal_order_number }}
@endif
@endif

<x-mail::button :url="config('app.url')">
View Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
