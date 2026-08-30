@extends('layouts.app')

@section('title', 'Terms of Service')

@section('content')
  <article class="mx-auto max-w-4xl space-y-8 text-gray-800 dark:text-gray-100">
    <header class="space-y-3">
      <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-[#A1A09A]">Boilerplate for legal review</p>
      <h1 class="text-3xl font-bold tracking-tight">Terms of Service</h1>
      <p class="text-sm text-gray-600 dark:text-[#A1A09A]">Last updated: June 16, 2026</p>
      <p class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">These Terms of Service are boilerplate for counsel review. They are not legal advice and should be refined and approved by counsel before production use.</p>
    </header>

    <section class="space-y-3"><h2 class="text-2xl font-semibold">Agreement to these terms</h2><p>These Terms of Service govern access to and use of {{ config('app.name') }}. By creating an account, submitting a pass request, administering the service, or otherwise using the service, you agree to these terms.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Eligibility and accounts</h2><p>You must provide accurate account and request information, keep your credentials secure, and promptly notify us of unauthorized account access. You are responsible for activity that occurs under your account unless caused by our failure to use reasonable security measures.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Permitted use</h2><p>You may use the service only for lawful pass request, review, administration, and related purposes authorized by us. You may not misuse the service, interfere with its operation, attempt unauthorized access, scrape or extract data except as permitted, submit fraudulent requests, or violate applicable law.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Pass requests and promo codes</h2><p>Submitting a pass request does not guarantee approval, availability, or receipt of any promo code, pass, benefit, or discount. Administrators may review, approve, deny, revoke, or adjust requests and promo code assignments according to program rules, eligibility, availability, and fraud-prevention needs.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">User content</h2><p>You retain ownership of information, answers, files, and other content you submit. You grant us a limited license to host, process, transmit, display, and use that content as necessary to provide, secure, support, and improve the service and administer related programs.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Privacy</h2><p>Our <a class="underline" href="{{ route('privacy') }}">Privacy Policy</a> explains how we collect, use, and share information. By using the service, you acknowledge that information will be handled as described there.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Third-party services</h2><p>The service may rely on or link to third-party services, such as hosting, email delivery, authentication, monitoring, storage, or external pass redemption services. We are not responsible for third-party services that we do not control.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Service changes and availability</h2><p>We may modify, suspend, or discontinue all or part of the service at any time. We will try to provide reasonable notice where practical, but we do not guarantee uninterrupted or error-free operation.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Disclaimers</h2><p>The service is provided “as is” and “as available.” To the fullest extent permitted by law, we disclaim warranties of merchantability, fitness for a particular purpose, title, non-infringement, and any warranties arising from course of dealing or usage of trade.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Limitation of liability</h2><p>To the fullest extent permitted by law, we will not be liable for indirect, incidental, special, consequential, exemplary, or punitive damages, or for lost profits, lost data, or business interruption. Our aggregate liability for claims relating to the service will not exceed the greater of the amount you paid to use the service in the 12 months before the claim or US $100.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Indemnification</h2><p>You agree to defend, indemnify, and hold us harmless from claims, losses, liabilities, damages, costs, and expenses arising from your misuse of the service, violation of these terms, violation of law, or infringement of third-party rights.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Termination</h2><p>We may suspend or terminate access if you violate these terms, create risk, or use the service in a way that may harm others or the service. You may stop using the service at any time.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Governing law and disputes</h2><p>These placeholder terms should be updated by counsel to specify governing law, venue, dispute-resolution procedures, arbitration terms if any, and jurisdiction-specific consumer rights that cannot be waived.</p></section>
    <section class="space-y-3"><h2 class="text-2xl font-semibold">Contact</h2><p>Questions about these terms can be sent to <a class="underline" href="mailto:{{ config('legal.privacy_contact') }}">{{ config('legal.privacy_contact') }}</a>.</p></section>
  </article>
@endsection
