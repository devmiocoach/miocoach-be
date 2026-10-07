export type MetricType =
    | 'weight'
    | 'body_fat'
    | 'waist'
    | 'chest'
    | 'hips'
    | 'arms'
    | 'thighs'
    | 'shoulders'
    | 'neck'
    | 'calves';

export type PhotoPose =
    | 'front'
    | 'back'
    | 'side_left'
    | 'side_right'
    | 'custom';

export type ChartRange = '1M' | '3M' | '6M' | '1Y' | 'all';

export interface ChartPoint {
    date: string;
    value: number;
}

export interface ChartSummary {
    first_value: number;
    last_value: number;
    delta: number;
    delta_percent: number;
    min: number;
    max: number;
}

export interface ChartGoal {
    target_value: number;
    target_date: string | null;
}

export interface ChartData {
    metric_type: MetricType;
    unit: string;
    points: ChartPoint[];
    goal: ChartGoal | null;
    summary: ChartSummary | null;
}

export interface BodyMeasurement {
    id: number;
    client_id: number;
    coach_id: number;
    metric_type: MetricType;
    value: number;
    measured_at: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface MeasurementGoal {
    id: number;
    client_id: number;
    coach_id: number;
    metric_type: MetricType;
    target_value: number;
    target_date: string | null;
}

export interface ProgressPhoto {
    id: number;
    client_id: number;
    coach_id: number;
    storage_key: string;
    thumbnail_key: string | null;
    pose: PhotoPose | null;
    taken_at: string;
    notes: string | null;
    created_at: string;
}

export interface MetricInfo {
    value: MetricType;
    label: string;
    unit: string;
}

export const METRICS: MetricInfo[] = [
    { value: 'weight', label: 'Peso', unit: 'kg' },
    { value: 'body_fat', label: 'BF%', unit: '%' },
    { value: 'waist', label: 'Vita', unit: 'cm' },
    { value: 'chest', label: 'Petto', unit: 'cm' },
    { value: 'hips', label: 'Fianchi', unit: 'cm' },
    { value: 'arms', label: 'Braccia', unit: 'cm' },
    { value: 'thighs', label: 'Gambe', unit: 'cm' },
    { value: 'shoulders', label: 'Spalle', unit: 'cm' },
    { value: 'neck', label: 'Collo', unit: 'cm' },
    { value: 'calves', label: 'Polpacci', unit: 'cm' },
];

export const RANGES: { value: ChartRange; label: string }[] = [
    { value: '1M', label: '1M' },
    { value: '3M', label: '3M' },
    { value: '6M', label: '6M' },
    { value: '1Y', label: '1A' },
    { value: 'all', label: 'Tutto' },
];
