export interface EventCard {
    id: string;
    name: string;
    description: string | null;
    type: string;
    status: string;
    address: string | null;
    city: string | null;
    country: string | null;
    timezone: string | null;
    starts_at_iso: string | null;
    starts_at_unix: number | null;
    latitude: number | null;
    longitude: number | null;
    venue: string | null;
    price: number | null;
    currency: string;
    images: string[];
    attendees_count: number;
}

export interface CityFacet {
    city: string;
    country: string;
    lat: number;
    lng: number;
}

export interface EventFacets {
    statuses: string[];
    types: string[];
    cities: CityFacet[];
}

export interface EventFilters {
    from: string | null;
    to: string | null;
    city: string | null;
    country: string | null;
    q: string | null;
    status: string | null;
    type: string | null;
}

export interface MapPoint {
    city: string;
    country: string;
    count: number;
    lat: number;
    lng: number;
}
