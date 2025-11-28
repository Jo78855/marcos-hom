export type Language = 'ar' | 'en';

export enum ItemType {
  TABLE = 'table',
  CABINET = 'cabinet'
}

export type TableSize = '100' | '150' | '200';
export type CabinetWidth = '40' | '60';

export enum FurnitureColor {
  WOOD_LIGHT = 'wood_light',
  WOOD_MEDIUM = 'wood_medium',
  WOOD_DARK = 'wood_dark',
  GREY_LIGHT = 'grey_light',
  GREY_DARK = 'grey_dark',
  WHITE = 'white',
  BLACK = 'black'
}

export enum HardwareType {
  NONE = 'none',
  HANDLE_GOLD = 'handle_gold',
  HANDLE_BLACK = 'handle_black',
  KNOB_GOLD = 'knob_gold',
  KNOB_BLACK = 'knob_black'
}

export interface FurnitureItem {
  id: string;
  type: ItemType;
  size: TableSize | CabinetWidth; // 100, 150, 200 for tables; 40, 60 for cabinets
  color: FurnitureColor;
  hardware?: HardwareType;
  position: number; // For sorting
}

export interface PriceConfig {
  tables: Record<TableSize, number>;
  cabinets: Record<CabinetWidth, number>;
  hardware: Record<HardwareType, number>;
}

export const PRICING: PriceConfig = {
  tables: {
    '100': 30,
    '150': 45,
    '200': 60
  },
  cabinets: {
    '40': 50,
    '60': 60
  },
  hardware: {
    'none': 0,
    'handle_gold': 5,
    'handle_black': 5,
    'knob_gold': 3,
    'knob_black': 3
  }
};