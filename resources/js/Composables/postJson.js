/**
 * Posts to a JSON endpoint without leaving the page, with the CSRF token Laravel keeps in a cookie.
 *
 * @param {string} url
 * @param {object|null} [body] sent as JSON when given
 * @returns {Promise<object>} the `data` of the answer
 * @throws {Error} when the server answers with an error status; the message is the server's, in Indonesian
 */
export async function postJson(url, body = null) {
    const token = decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) ?? [])[1] ?? '');

    const response = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token, ...(body === null ? {} : { 'Content-Type': 'application/json' }) },
        credentials: 'same-origin',
        body: body === null ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error((await response.json().catch(() => ({}))).message ?? 'Permintaan gagal.');
    }

    return (await response.json()).data;
}
