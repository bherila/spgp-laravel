export type ValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
  constructor(message: string, public readonly status: number, public readonly errors: ValidationErrors = {}) {
    super(message);
    this.name = 'ApiError';
  }
}

export interface ApiRequestOptions extends Omit<RequestInit, 'body' | 'credentials' | 'redirect'> {
  json?: unknown;
  body?: BodyInit | null;
  csrfToken?: string;
  errorMessage?: string;
  /** GET defaults to requiring JSON; mutations default to allowing no content. */
  allowEmpty?: boolean;
}

export function getCsrfToken(explicitToken?: string): string {
  return explicitToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function validationErrors(value: unknown): ValidationErrors {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return {};

  return Object.fromEntries(Object.entries(value).filter(
    (entry): entry is [string, string[]] => Array.isArray(entry[1]) && entry[1].every((message: unknown) => typeof message === 'string'),
  ));
}

const acceptedUnreadableMessage = 'The server accepted the request, but its response could not be read. Refresh to check the result before submitting again.';
const uncertainMutationMessage = 'The request may have been processed, but its result could not be confirmed. Refresh to check the result before submitting again.';

function isAbortError(error: unknown): boolean {
  return typeof error === 'object' && error !== null && 'name' in error && error.name === 'AbortError';
}

function fallbackMessage(status: number, message?: string): string {
  if (status === 401) return 'Please sign in again to continue.';
  if (status === 419) return 'Your session has expired. Refresh the page and try again.';
  return message ?? `Request failed (HTTP ${status}). Please try again.`;
}

/** Make a same-origin JSON request. Mutations are never retried automatically. */
export async function apiRequest<T = void>(url: string, options: ApiRequestOptions = {}): Promise<T> {
  const target = new URL(url, window.location.href);
  if (!['http:', 'https:'].includes(target.protocol) || target.origin !== window.location.origin || target.username || target.password) {
    throw new Error('API requests must use the same origin as this page.');
  }

  const { json, body: explicitBody, csrfToken, errorMessage, allowEmpty, ...requestOptions } = options;
  const method = (requestOptions.method ?? 'GET').toUpperCase();
  const isMutation = !['GET', 'HEAD', 'OPTIONS'].includes(method);
  const permitsEmpty = allowEmpty ?? (method === 'HEAD' || isMutation);
  const headers = new Headers(requestOptions.headers);
  headers.set('Accept', 'application/json');
  if (isMutation && !headers.has('X-CSRF-TOKEN')) {
    const token = getCsrfToken(csrfToken);
    if (token) headers.set('X-CSRF-TOKEN', token);
  }

  let body = explicitBody;
  if (body === undefined && json !== undefined) {
    body = JSON.stringify(json);
    headers.set('Content-Type', 'application/json');
  }

  let response: Response;
  try {
    response = await fetch(url, {
      ...requestOptions,
      method,
      headers,
      ...(body !== undefined ? { body } : {}),
      credentials: 'same-origin',
      redirect: 'error',
    });
  } catch (error) {
    if (isAbortError(error) || !isMutation) throw error;
    throw new ApiError(uncertainMutationMessage, 0);
  }

  if (response.ok && (method === 'HEAD' || response.status === 204)) {
    if (!permitsEmpty) throw new ApiError(isMutation
      ? acceptedUnreadableMessage
      : 'The server returned an empty response. Refresh the page and try again.', response.status);
    return undefined as T;
  }

  let text: string;
  try {
    text = await response.text();
  } catch (error) {
    if (isAbortError(error)) throw error;
    const message = response.ok && isMutation
      ? acceptedUnreadableMessage
      : fallbackMessage(response.status, errorMessage ?? 'The server response could not be read. Refresh the page and try again.');
    throw new ApiError(message, response.status);
  }
  const contentType = response.headers.get('Content-Type')?.split(';')[0]?.trim().toLowerCase() ?? '';
  const isJson = contentType === 'application/json' || contentType.endsWith('+json');
  let data: unknown;
  let validJson = text === '' && permitsEmpty;
  if (text !== '' && isJson) {
    try {
      data = JSON.parse(text) as unknown;
      validJson = true;
    } catch {
      // Never display a response body that might contain HTML or private server diagnostics.
    }
  }

  if (!response.ok) {
    const record = typeof data === 'object' && data !== null ? data as Record<string, unknown> : {};
    const message = typeof record.message === 'string' && record.message.trim() !== ''
      ? record.message
      : fallbackMessage(response.status, errorMessage);
    throw new ApiError(message, response.status, validationErrors(record.errors));
  }
  if (!validJson) {
    throw new ApiError(isMutation
      ? acceptedUnreadableMessage
      : 'The server returned an unexpected response. Refresh the page and try again.', response.status);
  }

  return data as T;
}
