import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/pt-br';

dayjs.extend(relativeTime);
dayjs.locale('pt-br');

export function useFormat() {
    const time   = (d) => (d ? dayjs(d).format('HH:mm') : '');
    const date   = (d) => (d ? dayjs(d).format('DD/MM/YYYY') : '');
    const dt     = (d) => (d ? dayjs(d).format('DD/MM/YYYY HH:mm') : '');
    const fromNow= (d) => (d ? dayjs(d).fromNow() : '');
    const phone  = (p) => {
        if (!p) return '';
        const c = String(p).replace(/\D/g, '');
        if (c.length === 13) return `+${c.slice(0,2)} (${c.slice(2,4)}) ${c.slice(4,9)}-${c.slice(9)}`;
        if (c.length === 11) return `(${c.slice(0,2)}) ${c.slice(2,7)}-${c.slice(7)}`;
        return p;
    };
    const initials = (n) => (n || '?').split(' ').map(s => s[0]).slice(0,2).join('').toUpperCase();

    return { time, date, dt, fromNow, phone, initials };
}
