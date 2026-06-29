import { api } from "./client";
import type { Event, EventListResponse, TicketType } from "./types";

export const eventsApi = {
  list: (page = 1) =>
    api.get<EventListResponse>(`/events?page=${page}`),

  show: (id: string) =>
    api.get<{ event: Event }>(`/events/${id}`),

  create: (data: {
    title: string;
    description: string;
    timezone: string;
    starts_at: string;
    ends_at: string;
    capacity: number;
  }) => api.post<{ event: Event }>("/events", data),

  update: (id: string, data: Partial<{
    title: string;
    description: string;
    timezone: string;
    starts_at: string;
    ends_at: string;
    capacity: number;
    status: string;
  }>) => api.put<{ event: Event }>(`/events/${id}`, data),

  destroy: (id: string) => api.delete<void>(`/events/${id}`),

  // Ticket types
  listTicketTypes: (eventId: string) =>
    api.get<{ ticket_types: TicketType[] }>(`/events/${eventId}/ticket-types`),

  createTicketType: (
    eventId: string,
    data: {
      kind: string;
      price: number;
      currency: string;
      quantity_total: number;
      sales_start?: string;
      sales_end?: string;
      group_size?: number;
      group_discount?: number;
    },
  ) => api.post<{ ticket_type: TicketType }>(`/events/${eventId}/ticket-types`, data),

  updateTicketType: (
    eventId: string,
    ticketTypeId: string,
    data: Partial<{
      kind: string;
      price: number;
      currency: string;
      quantity_total: number;
      sales_start: string;
      sales_end: string;
    }>,
  ) =>
    api.put<{ ticket_type: TicketType }>(
      `/events/${eventId}/ticket-types/${ticketTypeId}`,
      data,
    ),

  deleteTicketType: (eventId: string, ticketTypeId: string) =>
    api.delete<void>(`/events/${eventId}/ticket-types/${ticketTypeId}`),
};
