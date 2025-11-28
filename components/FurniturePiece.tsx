import React, { useState } from 'react';
import { FurnitureItem, ItemType, FurnitureColor, HardwareType } from '../types';
import { COLORS } from '../constants';
import { X, ArrowLeft, ArrowRight, GripVertical } from 'lucide-react';

interface Props {
  item: FurnitureItem;
  index: number;
  onRemove: (id: string) => void;
  onMove: (id: string, direction: 'left' | 'right') => void;
  lang: 'ar' | 'en';
  isHovered: boolean;
  setHovered: (id: string | null) => void;
  onDragStart: (index: number) => void;
  onDrop: (index: number) => void;
}

const FurniturePiece: React.FC<Props> = ({ 
  item, 
  index,
  onRemove, 
  onMove, 
  lang, 
  isHovered, 
  setHovered,
  onDragStart,
  onDrop
}) => {
  const [imageError, setImageError] = useState(false);

  // Calculate relative widths for visualization
  // Base unit: 1 meter = 100px (arbitrary scale for CSS)
  let widthClass = '';
  let displayWidth = '';

  if (item.type === ItemType.TABLE) {
    if (item.size === '100') widthClass = 'w-[100px]';
    if (item.size === '150') widthClass = 'w-[150px]';
    if (item.size === '200') widthClass = 'w-[200px]';
    displayWidth = `${Number(item.size) / 100}m`;
  } else {
    // Cabinets
    if (item.size === '40') widthClass = 'w-[40px]';
    if (item.size === '60') widthClass = 'w-[60px]';
    displayWidth = `${item.size}cm`;
  }

  // Height: Cabinets are tall (2m), Tables are short (~40-50cm)
  const heightClass = item.type === ItemType.CABINET ? 'h-[200px]' : 'h-[50px] mt-auto';

  const folder = item.type === ItemType.TABLE ? 'tables' : 'cabinets';
  const src = `/src/images/${folder}/${item.size}/${item.color}.png`;

  const renderHardware = () => {
    if (item.type !== ItemType.CABINET || !item.hardware || item.hardware === HardwareType.NONE) return null;

    const isGold = item.hardware.includes('gold');
    const isKnob = item.hardware.includes('knob');
    const colorClass = isGold ? 'bg-yellow-500 shadow-yellow-200' : 'bg-gray-800 shadow-gray-400';
    
    // Positioning: Center vertically, slightly to the right for "door" effect
    if (isKnob) {
      return (
        <div 
          className={`absolute top-1/2 right-[15%] w-3 h-3 rounded-full shadow-sm ${colorClass} z-10 transform -translate-y-1/2`}
          title={item.hardware}
        />
      );
    } else {
      // Handle
      return (
        <div 
          className={`absolute top-1/2 right-[15%] w-1.5 h-10 rounded-full shadow-sm ${colorClass} z-10 transform -translate-y-1/2`}
          title={item.hardware}
        />
      );
    }
  };

  return (
    <div 
      className={`relative group flex flex-col items-center justify-end transition-all duration-300 ${widthClass} mx-0.5 cursor-move`}
      onMouseEnter={() => setHovered(item.id)}
      onMouseLeave={() => setHovered(null)}
      draggable="true"
      onDragStart={(e) => {
        e.dataTransfer.effectAllowed = 'move';
        onDragStart(index);
        // Clean ghost image if needed, but default is usually fine
      }}
      onDragOver={(e) => {
        e.preventDefault(); // Necessary to allow dropping
        e.dataTransfer.dropEffect = 'move';
      }}
      onDrop={(e) => {
        e.preventDefault();
        onDrop(index);
      }}
    >
      {/* Controls Overlay */}
      {isHovered && (
        <div className="absolute -top-12 left-0 right-0 flex justify-center space-x-1 z-20">
           <div className="flex bg-white/90 backdrop-blur rounded-full p-1 shadow-lg border border-gray-200 items-center">
             <div className="cursor-grab p-1 text-gray-400 border-r border-gray-200 pr-1 mr-1">
                <GripVertical size={14} />
             </div>
             <button onClick={() => onMove(item.id, 'left')} className="p-1 hover:bg-gray-200 rounded-full text-gray-700">
               {lang === 'ar' ? <ArrowRight size={14} /> : <ArrowLeft size={14} />}
             </button>
             <button onClick={() => onRemove(item.id)} className="p-1 hover:bg-red-100 rounded-full text-red-600">
               <X size={14} />
             </button>
             <button onClick={() => onMove(item.id, 'right')} className="p-1 hover:bg-gray-200 rounded-full text-gray-700">
               {lang === 'ar' ? <ArrowLeft size={14} /> : <ArrowRight size={14} />}
             </button>
           </div>
        </div>
      )}

      {/* The Furniture Visual */}
      <div 
        className={`relative w-full ${heightClass} shadow-md overflow-hidden border border-black/5 flex items-center justify-center`}
        style={{ 
            backgroundColor: imageError ? COLORS[item.color].hex : 'transparent' 
        }}
      >
        {!imageError ? (
           <img 
            src={src} 
            alt={`${item.type} ${item.size}`} 
            className="w-full h-full object-cover"
            onError={() => setImageError(true)}
           />
        ) : (
          <div className={`w-full h-full ${COLORS[item.color].tailwind} opacity-90 flex flex-col items-center justify-center`}>
            {/* Visual texture hint */}
            <span className="text-[10px] text-white/70 font-bold uppercase tracking-widest opacity-50 rotate-90 whitespace-nowrap">
                {item.type} {item.size}
            </span>
          </div>
        )}
        
        {/* Cabinet Lighting Effect */}
        {item.type === ItemType.CABINET && (
          <div className="absolute inset-y-0 left-1/2 w-0.5 bg-yellow-400/30 blur-sm shadow-[0_0_10px_2px_rgba(250,204,21,0.3)]"></div>
        )}

        {/* Hardware (Knobs/Handles) */}
        {renderHardware()}
      </div>

      {/* Label below */}
      <div className="mt-2 text-[10px] text-gray-500 font-mono whitespace-nowrap bg-white/80 px-1 rounded shadow-sm">
        {displayWidth}
      </div>
    </div>
  );
};

export default FurniturePiece;