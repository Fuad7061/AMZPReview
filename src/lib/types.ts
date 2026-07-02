export interface MoreDeal {
  url: string;
  price: string;
  price_sort: number;
  merchant: string;
  condition: string;
  free_shipping: boolean;
}

export interface RawProduct {
  id: string;
  title: string;
  brand: string;
  price: string;
  price_sort: number;
  category: string;
  category_v2: string;
  features: string[];
  image_url: string;
  image_urls: string[];
  saving_basis: string;
  savings_percentage: number | "";
  savings_amount: number | "";
  free_shipping: boolean;
  condition: string;
  score: string;
  url: string;
  moreDeals?: MoreDeal[];
}

export interface Product extends RawProduct {
  index: number; // 1-based rank
  topProduct: boolean;
  bestValue: boolean;
  bestBudget: boolean;
}

export interface ApiResponse {
  created_timestamp: string;
  version: string;
  slugData: Record<string, unknown>;
  products: RawProduct[];
}

export interface PriceHistoryPoint {
  date: string;
  price: number;
}

export type SortOption = "relevance" | "discount" | "price_asc" | "price_desc";
