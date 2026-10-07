export interface TodaySession {
    id: number;
    starts_at: string;
    ends_at: string | null;
    status: string;
    service_type: string | null;
    service_type_label: string | null;
    client: {
        id: number;
        name: string;
        avatar: string | null;
    } | null;
    guest_name: string | null;
}

export interface Activity {
    id: number;
    event_type: string;
    metadata: Record<string, unknown> | null;
    created_at: string;
}

export interface DashboardProps {
    activeClientsCount: number;
    sessionsThisWeek: number;
    revenueThisMonth: number;
    expiringClientsCount: number;
    todaySessions: TodaySession[];
    expiringThisWeekCount: number;
    inactiveClientsCount: number;
    recentActivities: Activity[];
}
