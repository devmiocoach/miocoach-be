export interface CoachListingItem {
    id: number;
    slug: string;
    display_name: string;
    tagline: string | null;
    bio_excerpt: string;
    city: string | null;
    province: string | null;
    mode: 'online' | 'in_person' | 'hybrid' | null;
    gender: 'male' | 'female' | 'other' | null;
    specializations: { name: string; slug: string }[];
    languages: string[] | null;
    price_from_cents: number | null;
    years_of_experience: number | null;
    is_verified: boolean;
    profile_photo_url: string | null;
    lat: number | null;
    lng: number | null;
    rating: number | null;
    reviews_count: number;
}

export interface PublicCoachData {
    id: number;
    slug: string;
    display_name: string;
    bio: string | null;
    tagline: string | null;
    description: string | null;
    specializations: string[] | null;
    languages: string[] | null;
    mode: 'online' | 'in_person' | 'hybrid' | null;
    gender: 'male' | 'female' | 'other' | null;
    city: string;
    province: string | null;
    lat: number | null;
    lng: number | null;
    hourly_rate: string | null;
    price_per_session: string | null;
    single_session_price_cents: number | null;
    free_first_session: boolean;
    contact_options: {
        free_consultation: boolean;
        direct_message: boolean;
        direct_booking: boolean;
    };
    cancellation_window_hours: number | null;
    session_duration_default: string | null;
    years_of_experience: number | null;
    is_verified: boolean;
    profile_photo_url: string | null;
    cover_photo_url: string | null;
    gallery_photos: string[] | null;
    intro_video_url: string | null;
    social_links: Record<string, string> | null;
    website_url: string | null;
    rating: number | null;
    reviews_count: number;
    certifications: CertificationData[];
    packages: CoachPackageData[];
    availability: AvailabilitySlot[];
    seo: {
        title: string;
        description: string;
        og_image: string | null;
    };
}

export interface CertificationData {
    id: number;
    name: string;
    issuer: string | null;
    issued_at: string | null;
    verified_at: string | null;
}

export interface CoachPackageData {
    id: number;
    name: string;
    description: string | null;
    sessions_count: number;
    price_cents: number;
    price_in_euros: number;
    validity_days: number;
    is_active: boolean;
    sort_order: number;
}

export interface AvailabilitySlot {
    id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    duration_minutes: number;
    is_recurring: boolean;
}

export interface ReviewData {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    is_verified: boolean;
    reviewer_name: string;
    created_at: string;
}

export interface TimeSlotData {
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    available: boolean;
}

export interface BookingFormData {
    coach_id: number;
    service_type: string;
    package_id: number | null;
    slot_starts_at: string;
    duration_minutes: number;
    guest_name: string;
    guest_email: string;
    guest_phone: string;
}

export interface NominatimSuggestion {
    display_name: string;
    name: string;
    lat: string;
    lon: string;
    address?: {
        city?: string;
        town?: string;
        village?: string;
        municipality?: string;
    };
}

export interface SearchFilters {
    search: string;
    city: string;
    mode: string[];
    price_min: number | null;
    price_max: number | null;
    specialization: string[];
    gender: string | null;
    rating_min: number | null;
    available_days: number[];
    time_slots: string[];
    sort: string;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
