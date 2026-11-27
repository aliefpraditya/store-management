/**
 * The COD rows of an end-of-shift report, shared by the page of the report and its text for the printer, so the two cannot
 * drift apart. A row's `value` is an amount (`isMoney`) or a count, left unformatted for each to format its own way.
 *
 * @typedef {{label: string, value: string|number, isMoney: boolean}} ShiftReportRow
 */

const COD_RECEIVED_METHODS = [
    { key: 'cash', label: 'Tunai' },
    { key: 'transfer', label: 'Transfer' },
    { key: 'qris', label: 'QRIS' },
    { key: 'edc', label: 'EDC' },
];

/**
 * The COD receipts of the shift, none when there were none. COD is not money in the drawer. The blind report counts them
 * (`delivered_count`), the full one adds what they are worth (`delivered.count` and `.amount`).
 *
 * @param {{is_blind: boolean, cod: object}} report
 * @returns {ShiftReportRow[]}
 */
export function codRowsOf(report) {
    const { cod, is_blind: isBlind } = report;
    const delivered = isBlind ? cod.delivered_count : cod.delivered.count;
    const undelivered = isBlind ? cod.undelivered_count : cod.undelivered.count;

    if (delivered + undelivered === 0) {
        return [];
    }

    return isBlind
        ? [
            { label: 'Nota COD sudah diantar', value: delivered, isMoney: false },
            { label: 'Nota COD belum diantar', value: undelivered, isMoney: false },
        ]
        : [
            { label: `COD sudah diantar (${delivered} nota)`, value: cod.delivered.amount, isMoney: true },
            { label: `COD belum diantar (${undelivered} nota)`, value: cod.undelivered.amount, isMoney: true },
        ];
}

/**
 * The COD money this shift received (the full report only; the blind one has no amounts), by method, none when nothing was
 * received. Received cash is in the drawer, the rest went to an account.
 *
 * @param {{is_blind: boolean, cod_received?: object}} report
 * @returns {ShiftReportRow[]}
 */
export function codReceivedRowsOf(report) {
    const received = report.cod_received;

    if (report.is_blind || !(Number(received?.total) > 0)) {
        return [];
    }

    return [
        { label: 'Pelunasan COD diterima', value: received.total, isMoney: true },
        ...COD_RECEIVED_METHODS.filter((method) => Number(received[method.key]) > 0).map((method) => ({ label: `· ${method.label}`, value: received[method.key], isMoney: true })),
    ];
}
