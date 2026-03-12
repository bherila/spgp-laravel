import * as Sentry from '@sentry/browser';

declare global {
    interface Window {
        SENTRY_DSN?: string;
    }
}

const dsn = window.SENTRY_DSN;

if (dsn) {
    Sentry.init({
        dsn,
        tracesSampleRate: 1.0,
        integrations: [
            Sentry.browserTracingIntegration(),
            Sentry.replayIntegration({ maskAllText: true, blockAllMedia: true }),
        ],
    });
}
