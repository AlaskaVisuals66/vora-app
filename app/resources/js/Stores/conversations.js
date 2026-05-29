import { defineStore } from 'pinia';
import axios from 'axios';

export const useConversationsStore = defineStore('conversations', {
    state: () => ({
        tickets: [],
        loading: false,
        filters: { status: '', sector_id: null, search: '' },
        active: null,
        messages: [],
        messagesLoading: false,
        typingUsers: {},
        // Sector permission for realtime gating. isAdmin → see everything (matches
        // the server, which applies no sector filter for admins). Non-admins are
        // restricted to sectorIds (their actual sector membership).
        access: { isAdmin: true, sectorIds: null },
    }),

    actions: {
        setAccess(isAdmin, sectorIds) {
            this.access = {
                isAdmin: !!isAdmin,
                sectorIds: isAdmin ? null : (sectorIds || []).map(Number),
            };
        },

        canSeeSector(sectorId) {
            if (this.access.isAdmin || this.access.sectorIds === null) return true;
            if (sectorId === null || sectorId === undefined) return false;
            return this.access.sectorIds.includes(Number(sectorId));
        },

        async fetchTickets() {
            this.loading = true;
            try {
                const params = { ...this.filters, per_page: 500 };
                if (!params.sector_id) delete params.sector_id;
                if (!params.status)    delete params.status;
                if (!params.search)    delete params.search;
                const { data } = await axios.get('/api/v1/tickets', { params });
                this.tickets = data.data || data;
            } finally {
                this.loading = false;
            }
        },

        async openTicket(id) {
            this.active = this.tickets.find((t) => t.id === id) || null;
            this.messages = [];
            this.messagesLoading = true;
            try {
                const [{ data: ticket }, { data: msgs }] = await Promise.all([
                    axios.get(`/api/v1/tickets/${id}`),
                    axios.get(`/api/v1/tickets/${id}/messages`),
                ]);
                this.active = ticket.data || ticket;
                this.messages = msgs.data || msgs;
            } finally {
                this.messagesLoading = false;
            }
        },

        async sendMessage(body) {
            if (!this.active || !body?.trim()) return;
            const { data } = await axios.post(`/api/v1/tickets/${this.active.id}/messages`, { body });
            const msg = data.data || data;
            this.messages.push(msg);
        },

        pushIncomingMessage(msg) {
            if (!msg || !msg.id) return;
            if (this.active && msg.ticket_id === this.active.id) {
                if (!this.messages.some((m) => m.id === msg.id)) {
                    this.messages.push(msg);
                }
            }
            const t = this.tickets.find((t) => t.id === msg.ticket_id);
            if (t) t.last_message_at = msg.timestamp || msg.sent_at || msg.delivered_at || msg.created_at;
        },

        upsertTicket(ticket) {
            if (!ticket || !ticket.id) return;
            const ticketSectorId = ticket.sector_id ?? ticket.sector?.id ?? null;
            const idx = this.tickets.findIndex((t) => t.id === ticket.id);

            // Sector permission gate: a non-admin must never receive a ticket
            // outside their sectors via realtime — the initial list is already
            // scoped server-side, but live events broadcast tenant-wide. If a
            // ticket leaves the user's sectors (e.g. transferred away), drop it.
            if (!this.canSeeSector(ticketSectorId)) {
                if (idx >= 0) this.tickets.splice(idx, 1);
                return;
            }

            const sectorFilter = this.filters.sector_id;
            const matchesSector = !sectorFilter || ticketSectorId === sectorFilter;
            if (idx >= 0) {
                if (!matchesSector) { this.tickets.splice(idx, 1); return; }
                this.tickets[idx] = { ...this.tickets[idx], ...ticket };
            } else if (matchesSector) {
                this.tickets.unshift(ticket);
            }
        },

        setTyping(ticketId, userId, name) {
            const key = `${ticketId}:${userId}`;
            this.typingUsers[key] = { name, at: Date.now() };
            setTimeout(() => {
                if (this.typingUsers[key] && Date.now() - this.typingUsers[key].at >= 3000) {
                    delete this.typingUsers[key];
                }
            }, 3500);
        },

        async closeActive() {
            if (!this.active) return;
            const id = this.active.id;
            await axios.post(`/api/v1/tickets/${id}/close`);
            this.tickets = this.tickets.filter((t) => t.id !== id);
            this.active = null;
        },

        async transferToSector(sectorId) {
            if (!this.active) return;
            await axios.post(`/api/v1/tickets/${this.active.id}/transfer/sector`, { sector_id: sectorId });
        },

        async transferToUser(userId) {
            if (!this.active) return;
            await axios.post(`/api/v1/tickets/${this.active.id}/transfer/user`, { user_id: userId });
        },
    },
});
