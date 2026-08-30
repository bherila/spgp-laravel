@extends('layouts.app')

@section('title', 'Privacy Policy')

@section('content')
  <article class="mx-auto max-w-4xl space-y-8 text-gray-800 dark:text-gray-100">
    <header class="space-y-3">
      <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-[#A1A09A]">Boilerplate for legal review</p>
      <h1 class="text-3xl font-bold tracking-tight">Privacy Policy</h1>
      <p class="text-sm text-gray-600 dark:text-[#A1A09A]">Last updated: June 16, 2026</p>
      <p class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
        This Privacy Policy is provided as a practical starting point for California Consumer Privacy Act (CCPA/CPRA) and General Data Protection Regulation (GDPR) review. It is not legal advice and should be reviewed and approved by counsel before production use.
      </p>
    </header>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">Who we are</h2>
      <p>{{ config('app.name') }} provides an online pass request and administration service. References to “we,” “us,” and “our” mean the organization operating this application.</p>
      <p>For privacy questions or requests, contact us at <a class="underline" href="mailto:{{ config('legal.privacy_contact') }}">{{ config('legal.privacy_contact') }}</a>.</p>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">Information we collect</h2>
      <p>Depending on how you use the service, we may collect the following categories of information:</p>
      <ul class="list-disc space-y-2 pl-6">
        <li><strong>Account information</strong>, such as name, email address, login credentials, role, invite code details, and authentication audit records.</li>
        <li><strong>Pass request information</strong>, such as season selections, answers submitted with a request, eligibility or household details you provide, promo code assignment history, redemption status, and related dates.</li>
        <li><strong>Communications</strong>, such as email delivery logs, support messages, and administrative notes.</li>
        <li><strong>Technical information</strong>, such as IP address, device and browser details, log data, cookies, session identifiers, and security telemetry.</li>
        <li><strong>Files or attachments</strong> that you choose to upload where the service makes that feature available.</li>
      </ul>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">How we use information</h2>
      <ul class="list-disc space-y-2 pl-6">
        <li>Provide, secure, maintain, and improve the service.</li>
        <li>Create and manage accounts, authenticate users, and prevent unauthorized access.</li>
        <li>Process pass requests, assign promo codes, send notifications, and support administrators.</li>
        <li>Respond to questions, troubleshoot issues, and enforce our Terms of Service.</li>
        <li>Comply with legal obligations, preserve records, and protect our rights and the rights of others.</li>
      </ul>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">Legal bases for processing</h2>
      <p>Where GDPR or similar laws apply, our legal bases may include performance of a contract, legitimate interests in operating and securing the service, compliance with legal obligations, consent where requested, and protection of vital interests where applicable.</p>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">How we share information</h2>
      <p>We do not sell personal information. We may share information with service providers that help us host, secure, analyze, and operate the service; email and notification providers; administrators authorized by the organization; professional advisers; authorities when legally required; or another entity as part of a merger, acquisition, financing, or similar transaction.</p>
      <p>If CCPA/CPRA applies, we do not knowingly sell or share personal information for cross-context behavioral advertising. If that changes, we will update this policy and provide required choices.</p>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">Cookies and similar technologies</h2>
      <p>We may use cookies, local storage, and similar technologies for authentication, security, user preferences, analytics, and application functionality. You can configure your browser to block some cookies, but parts of the service may not work properly.</p>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">Retention</h2>
      <p>We retain information for as long as reasonably necessary to provide the service, administer pass programs, comply with legal obligations, resolve disputes, enforce agreements, and maintain security. Retention periods may vary depending on the type of record and operational requirements.</p>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">Your privacy rights</h2>
      <p>Depending on your location, you may have rights to request access, correction, deletion, portability, restriction, objection, withdrawal of consent, or appeal of certain decisions. California residents may also request details about categories of personal information collected, sources, purposes, disclosures, and retention, and may have rights to limit use of sensitive personal information where applicable.</p>
      <p>To exercise a right, contact <a class="underline" href="mailto:{{ config('legal.privacy_contact') }}">{{ config('legal.privacy_contact') }}</a>. We may need to verify your identity before responding. You may also have the right to contact your local data protection authority.</p>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">International transfers</h2>
      <p>If information is processed outside your jurisdiction, we will use appropriate safeguards where required, such as contractual protections or other lawful transfer mechanisms.</p>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">Children</h2>
      <p>The service is not intended for children under 13, and we do not knowingly collect personal information from children under 13. If you believe a child provided information without appropriate consent, contact us so we can review and delete it as required.</p>
    </section>

    <section class="space-y-3">
      <h2 class="text-2xl font-semibold">Changes to this policy</h2>
      <p>We may update this Privacy Policy from time to time. Material changes will be posted in the service or otherwise communicated as required by law.</p>
    </section>
  </article>
@endsection
