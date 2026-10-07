export interface ClientListItem {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    avatar_url: string | null;
    tags: string[];
    status: 'active' | 'suspended' | 'inactive';
    subscription_expires_at: string | null;
    subscription_sessions_remaining: number | null;
    next_booking: {
        id: number;
        starts_at: string;
    } | null;
}

export interface ClientsIndexProps {
    clients: ClientListItem[];
    availableTags: string[];
}

export interface ClientNote {
    id: number;
    content: string;
    coach_name: string | null;
    created_at: string;
}

export interface ClientDetail {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    avatar_url: string | null;
    tags: string[];
    status: 'active' | 'suspended' | 'inactive';
    birth_date: string | null;
    gender: string | null;
    height_cm: number | null;
    weight_kg: number | null;
    goals: string[] | null;
    objective: string | null;
    fitness_level: string | null;
    joined_at: string | null;
    subscription_expires_at: string | null;
    subscription_sessions_remaining: number | null;
    anamnesis: string | null;
    next_booking: {
        id: number;
        starts_at: string;
        service_type: string | null;
    } | null;
    recent_sessions: Array<{
        id: number;
        starts_at: string;
        service_type: string | null;
        status: string;
    }>;
}

export interface ClientShowProps {
    client: ClientDetail;
    notes: ClientNote[];
}
