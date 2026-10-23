export function mockTextResponse(text: string, status = 200): Response {
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: new Headers({ 'Content-Type': 'text/html; charset=utf-8' }),
    text: jest.fn().mockResolvedValue(text),
  } as unknown as Response;
}

export function mockJsonResponse(payload: unknown, status = 200): Response {
  const response = mockTextResponse(JSON.stringify(payload), status);
  response.headers.set('Content-Type', 'application/json');
  return response;
}
