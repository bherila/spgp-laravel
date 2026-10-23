import { ApiError, apiRequest } from '@/lib/apiClient';

import { mockJsonResponse, mockTextResponse } from './helpers/http';

describe('apiRequest', () => {
  beforeEach(() => {
    document.head.innerHTML = '<meta name="csrf-token" content="page-token">';
    globalThis.fetch = jest.fn().mockResolvedValue(mockJsonResponse({ message: 'ok' }));
  });

  function requestOptions(): RequestInit {
    return (globalThis.fetch as jest.Mock).mock.calls[0][1] as RequestInit;
  }

  it('uses same-origin credentials and JSON acceptance without a CSRF header on reads', async () => {
    await expect(apiRequest('/api/example')).resolves.toEqual({ message: 'ok' });
    expect(requestOptions()).toMatchObject({ method: 'GET', credentials: 'same-origin', redirect: 'error' });
    const headers = new Headers(requestOptions().headers);
    expect(headers.get('Accept')).toBe('application/json');
    expect(headers.has('X-CSRF-TOKEN')).toBe(false);
  });

  it('encodes JSON and applies the page CSRF token to mutations', async () => {
    await apiRequest('/api/example', { method: 'patch', json: { name: 'Alex' } });
    const options = requestOptions();
    expect(options.method).toBe('PATCH');
    expect(options.body).toBe('{"name":"Alex"}');
    const headers = new Headers(options.headers);
    expect(headers.get('X-CSRF-TOKEN')).toBe('page-token');
    expect(headers.get('Content-Type')).toBe('application/json');
  });

  it('preserves explicit headers, token overrides and abort signals', async () => {
    const controller = new AbortController();
    await apiRequest('/api/example', {
      method: 'POST',
      csrfToken: 'explicit-token',
      signal: controller.signal,
      headers: { 'X-Custom': 'value' },
    });
    expect(requestOptions().signal).toBe(controller.signal);
    const headers = new Headers(requestOptions().headers);
    expect(headers.get('X-CSRF-TOKEN')).toBe('explicit-token');
    expect(headers.get('X-Custom')).toBe('value');
  });

  it('passes FormData unchanged without adding a multipart content type', async () => {
    const body = new FormData();
    body.append('file', new Blob(['content']), 'file.txt');
    await apiRequest('/api/example', { method: 'POST', body });
    expect(requestOptions().body).toBe(body);
    expect(new Headers(requestOptions().headers).has('Content-Type')).toBe(false);
  });

  it.each(['https://external.example/api', '//external.example/api', 'data:application/json,{}', `blob:${window.location.origin}/example`])('rejects external URL %s before making a request', async (url) => {
    await expect(apiRequest(url, { method: 'POST', json: {} })).rejects.toThrow('same origin');
    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  it('reads the response body once and keeps Laravel validation errors', async () => {
    const response = mockJsonResponse({ message: 'Please check the email.', errors: { email: ['Email is invalid.'] } }, 422);
    (globalThis.fetch as jest.Mock).mockResolvedValue(response);
    await expect(apiRequest('/api/example')).rejects.toMatchObject({
      name: 'ApiError', status: 422, message: 'Please check the email.', errors: { email: ['Email is invalid.'] },
    });
    expect(response.text).toHaveBeenCalledTimes(1);
  });

  it.each([[401, 'sign in again'], [419, 'session has expired']])('explains HTTP %s without rendering HTML', async (status, message) => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockTextResponse('<html>private details</html>', status as number));
    await expect(apiRequest('/api/example', { errorMessage: 'Operation failed.' })).rejects.toThrow(message as string);
  });

  it('uses a safe contextual message for a non-JSON server failure', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockTextResponse('<html>private details</html>', 500));
    await expect(apiRequest('/api/example', { errorMessage: 'Could not save request.' })).rejects.toMatchObject({
      status: 500, message: 'Could not save request.',
    });
  });

  it('rejects HTML and malformed JSON even when the HTTP status is successful', async () => {
    (globalThis.fetch as jest.Mock).mockResolvedValueOnce(mockTextResponse('<html>login</html>'));
    await expect(apiRequest('/api/example')).rejects.toBeInstanceOf(ApiError);
    const malformed = mockTextResponse('{invalid');
    malformed.headers.set('Content-Type', 'application/json');
    (globalThis.fetch as jest.Mock).mockResolvedValueOnce(malformed);
    await expect(apiRequest('/api/example')).rejects.toThrow('unexpected response');
  });

  it.each([200, 204])('rejects empty HTTP %s GET responses unless explicitly allowed', async (status) => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockTextResponse('', status));
    await expect(apiRequest('/api/example')).rejects.toBeInstanceOf(ApiError);
    await expect(apiRequest('/api/example', { allowEmpty: true })).resolves.toBeUndefined();
  });

  it.each([200, 204])('accepts empty HTTP %s mutation responses', async (status) => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockTextResponse('', status));
    await expect(apiRequest('/api/example', { method: 'DELETE' })).resolves.toBeUndefined();
  });

  it.each([200, 204])('rejects empty HTTP %s mutations when JSON data is required', async (status) => {
    (globalThis.fetch as jest.Mock).mockResolvedValue(mockTextResponse('', status));
    await expect(apiRequest('/api/emails', { method: 'POST', allowEmpty: false })).rejects.toThrow('Refresh to check the result before submitting again.');
  });

  it('accepts a successful HEAD response without reading its body', async () => {
    const response = mockTextResponse('');
    (globalThis.fetch as jest.Mock).mockResolvedValue(response);
    await expect(apiRequest('/api/example', { method: 'HEAD' })).resolves.toBeUndefined();
    expect(response.text).not.toHaveBeenCalled();
  });

  it.each(['<html>login</html>', '{malformed'])('does not encourage repeating an accepted mutation with an unreadable response', async (text) => {
    const response = mockTextResponse(text);
    if (text.startsWith('{')) response.headers.set('Content-Type', 'application/json');
    (globalThis.fetch as jest.Mock).mockResolvedValue(response);
    await expect(apiRequest('/api/emails', { method: 'POST' })).rejects.toMatchObject({
      status: 200,
      message: 'The server accepted the request, but its response could not be read. Refresh to check the result before submitting again.',
    });
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });

  it('reports uncertain mutation results when response body reading fails', async () => {
    const response = mockJsonResponse({ sent: 1 });
    (response.text as jest.Mock).mockRejectedValue(new TypeError('Connection lost'));
    (globalThis.fetch as jest.Mock).mockResolvedValue(response);
    await expect(apiRequest('/api/emails', { method: 'POST' })).rejects.toThrow('Refresh to check the result before submitting again.');
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });

  it('reports uncertain mutation results after a network failure without retrying', async () => {
    (globalThis.fetch as jest.Mock).mockRejectedValue(new TypeError('Failed to fetch'));
    await expect(apiRequest('/api/emails', { method: 'POST' })).rejects.toMatchObject({
      status: 0,
      message: 'The request may have been processed, but its result could not be confirmed. Refresh to check the result before submitting again.',
    });
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });

  it('preserves AbortError during response body reading', async () => {
    const response = mockJsonResponse({ sent: 1 });
    const aborted = new DOMException('Aborted', 'AbortError');
    (response.text as jest.Mock).mockRejectedValue(aborted);
    (globalThis.fetch as jest.Mock).mockResolvedValue(response);
    await expect(apiRequest('/api/emails', { method: 'POST' })).rejects.toBe(aborted);
  });

  it('preserves cancellation errors and never retries a failed email mutation', async () => {
    const aborted = new DOMException('Aborted', 'AbortError');
    (globalThis.fetch as jest.Mock).mockRejectedValue(aborted);
    await expect(apiRequest('/api/emails', { method: 'POST', json: {} })).rejects.toBe(aborted);
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
  });
});
