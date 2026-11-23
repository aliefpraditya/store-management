import { ref } from 'vue';
import { Notify } from 'quasar';
import { printSheet } from './printSheet';

/**
 * The file name the server gave: the UTF-8 one when the name has characters outside ASCII (the other is then a degraded
 * copy), else the plain one.
 * @param {string} contentDisposition
 * @returns {string}
 */
function fileNameOf(contentDisposition) {
    const encodedName = /filename\*=UTF-8''([^;]+)/i.exec(contentDisposition)?.[1];

    return encodedName ? decodeURIComponent(encodedName) : (/filename="?([^";]+)"?/.exec(contentDisposition)?.[1] ?? 'Laporan Penjualan.xlsx');
}

/**
 * Prints the detailed sales report or saves it as an Excel file, for the period of the page and the payment method it is
 * narrowed to.
 *
 * @param {() => {detail_sheet: string, detail_export: string}} getUrls The addresses of the sheet and of the file, for the period on screen.
 * @param {() => string} getMethod The payment method the report is narrowed to; empty for every receipt.
 */
export function useDetailReportOutput(getUrls, getMethod) {
    /** Whether the Excel file is being made, for the button to show it is busy. */
    const isDownloadingExcel = ref(false);

    /** The address of a detailed report, narrowed to the method chosen. */
    function narrowed(baseUrl) {
        const url = new URL(baseUrl, window.location.origin);

        if (getMethod() !== '') {
            url.searchParams.set('method', getMethod());
        }

        return url.toString();
    }

    const printDetailReport = () => printSheet(() => narrowed(getUrls().detail_sheet));

    /**
     * Fetches the Excel file and saves it. The server works the whole report out before it sends the first byte, which can
     * take a while for a long period, and a plain link would show nothing meanwhile; fetching it here lets the page say it is
     * working. The size is not known ahead, so the indicator does not count up.
     */
    async function downloadDetailExcel() {
        if (isDownloadingExcel.value) {
            return;
        }

        isDownloadingExcel.value = true;
        const dismissNotice = Notify.create({ spinner: true, position: 'top', timeout: 0, message: 'Menyiapkan file Excel, mohon tunggu...' });

        try {
            const response = await fetch(narrowed(getUrls().detail_export), { credentials: 'same-origin' });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const fileName = fileNameOf(response.headers.get('Content-Disposition') ?? '');
            const fileUrl = URL.createObjectURL(await response.blob());
            const link = Object.assign(document.createElement('a'), { href: fileUrl, download: fileName });

            link.click();
            URL.revokeObjectURL(fileUrl);
            Notify.create({ type: 'positive', position: 'top', message: 'File Excel selesai diunduh.' });
        } catch (error) {
            Notify.create({ type: 'negative', position: 'top', message: `File Excel gagal dibuat (${error.message}). Coba lagi.` });
        } finally {
            dismissNotice();
            isDownloadingExcel.value = false;
        }
    }

    return { isDownloadingExcel, printDetailReport, downloadDetailExcel };
}
