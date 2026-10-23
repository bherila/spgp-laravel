/** Read the JSON data embedded by the page's Blade shell. */
export function readPageData<T>(id = 'page-data'): T {
  const element = document.getElementById(id);
  if (!element || element.getAttribute('type') !== 'application/json') {
    throw new Error(`Missing page data: ${id}`);
  }

  return JSON.parse(element.textContent ?? '') as T;
}
