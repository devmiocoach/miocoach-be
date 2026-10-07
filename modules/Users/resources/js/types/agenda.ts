export interface AgendaBooking {
    id: number;
    client_id: number | null;
    client: { id: number; name: string | null; avatar: string | null } | null;
    guest_name: string | null;
    starts_at: string;
    ends_at: string | null;
    duration_minutes: number;
    status: string;
    service_type: string | null;
    service_type_label: string | null;
    notes: string | null;
    price: number | null;
}

export interface AgendaAvailability {
    day_of_week: number;
    start_time: string;
    end_time: string;
}

export interface AgendaClient {
    id: number;
    name: string | null;
}

export interface ServiceType {
    value: string;
    label: string;
}

export interface AgendaProps {
    bookings: AgendaBooking[];
    weekStart: string;
    weekEnd: string;
    availabilities: AgendaAvailability[];
    clients: AgendaClient[];
    serviceTypes: ServiceType[];
    defaultDuration: number;
}
