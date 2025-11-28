import React, { useState, useEffect } from 'react';
import { GoogleGenAI } from "@google/genai";
import { ItemType, TableSize, CabinetWidth, FurnitureColor, HardwareType, FurnitureItem, PRICING, Language } from '../types';
import { TRANSLATIONS, COLORS, WHATSAPP_NUMBER } from '../constants';
import FurniturePiece from './FurniturePiece';
import { Plus, ShoppingCart, Share2, Undo2, Video, Loader2, Upload, X } from 'lucide-react';

// Simple ID generator since we can't use 'uuid' package in this constrained env
const generateId = () => Math.random().toString(36).substr(2, 9);

interface Props {
  lang: Language;
  onBack: () => void;
}

const Designer: React.FC<Props> = ({ lang, onBack }) => {
  const t = TRANSLATIONS[lang];
  const isRTL = lang === 'ar';
  
  // State
  const [items, setItems] = useState<FurnitureItem[]>([]);
  const [wallColor, setWallColor] = useState<string>('#f3f4f6');
  const [selectedType, setSelectedType] = useState<ItemType>(ItemType.TABLE);
  const [selectedSize, setSelectedSize] = useState<TableSize | CabinetWidth>('150');
  const [selectedColor, setSelectedColor] = useState<FurnitureColor>(FurnitureColor.WOOD_MEDIUM);
  const [selectedHardware, setSelectedHardware] = useState<HardwareType>(HardwareType.NONE);
  const [hoveredId, setHoveredId] = useState<string | null>(null);
  
  // DnD State
  const [draggedIndex, setDraggedIndex] = useState<number | null>(null);

  // Veo State
  const [showVeo, setShowVeo] = useState(false);
  const [veoImage, setVeoImage] = useState<string | null>(null);
  const [veoPrompt, setVeoPrompt] = useState('');
  const [veoRatio, setVeoRatio] = useState<'16:9' | '9:16'>('16:9');
  const [veoLoading, setVeoLoading] = useState(false);
  const [veoVideo, setVeoVideo] = useState<string | null>(null);

  // Reset size and defaults when type changes
  useEffect(() => {
    if (selectedType === ItemType.TABLE) {
      setSelectedSize('150');
    } else {
      setSelectedSize('40');
      // Reset hardware to none by default when switching to cabinet, or keep selection if user prefers sticky settings?
      // Let's keep selection to avoid annoyance, but logic implies only cabinets use it.
    }
  }, [selectedType]);

  const handleAddItem = () => {
    const newItem: FurnitureItem = {
      id: generateId(),
      type: selectedType,
      size: selectedSize as any,
      color: selectedColor,
      hardware: selectedType === ItemType.CABINET ? selectedHardware : undefined,
      position: items.length
    };
    setItems([...items, newItem]);
  };

  const handleRemoveItem = (id: string) => {
    setItems(items.filter(item => item.id !== id));
  };

  const handleMoveItem = (id: string, direction: 'left' | 'right') => {
    const index = items.findIndex(i => i.id === id);
    if (index === -1) return;

    const newIndex = direction === 'left' ? index - 1 : index + 1;
    
    if (newIndex >= 0 && newIndex < items.length) {
      const newItems = [...items];
      const temp = newItems[index];
      newItems[index] = newItems[newIndex];
      newItems[newIndex] = temp;
      setItems(newItems);
    }
  };

  // Drag and Drop Logic
  const handleDragStart = (index: number) => {
    setDraggedIndex(index);
  };

  const handleDrop = (targetIndex: number) => {
    if (draggedIndex === null || draggedIndex === targetIndex) return;
    
    const newItems = [...items];
    const [movedItem] = newItems.splice(draggedIndex, 1);
    newItems.splice(targetIndex, 0, movedItem);
    
    // Update position properties just in case (though we rely on array order for rendering)
    const updatedItems = newItems.map((item, idx) => ({ ...item, position: idx }));
    setItems(updatedItems);
    setDraggedIndex(null);
  };

  const calculateTotal = () => {
    return items.reduce((total, item) => {
      let itemPrice = 0;
      if (item.type === ItemType.TABLE) {
        itemPrice = PRICING.tables[item.size as TableSize];
      } else {
        itemPrice = PRICING.cabinets[item.size as CabinetWidth];
      }
      
      // Add hardware price
      if (item.hardware) {
        itemPrice += PRICING.hardware[item.hardware];
      }
      
      return total + itemPrice;
    }, 0);
  };

  const calculateTotalLength = () => {
     let lengthCm = 0;
     items.forEach(item => {
        if(item.type === ItemType.TABLE) lengthCm += Number(item.size);
        if(item.type === ItemType.CABINET) lengthCm += Number(item.size);
     });
     
     // Note: If combining cabinets and tables side-by-side, total length is just sum.
     // If cabinets are above tables, this logic would need to be more complex (layer aware).
     // For now, assuming linear layout as per "TV Unit" typical design strips.
     
     return lengthCm / 100; // Return in meters
  };

  const generateWhatsAppLink = () => {
    const total = calculateTotal();
    const tableLen = calculateTotalLength();
    
    let message = lang === 'en' 
      ? `Hi Marcos Home, I'd like to order a custom TV Unit design:\n` 
      : `مرحباً ماركوس هوم، أرغب بطلب تصميم وحدة تلفاز:\n`;

    message += `\n------------------\n`;
    
    items.forEach((item, idx) => {
      const typeLabel = item.type === ItemType.TABLE ? (lang === 'en' ? 'Table' : 'طاولة') : (lang === 'en' ? 'Cabinet' : 'كبت');
      const sizeUnit = item.type === ItemType.TABLE ? (lang === 'en' ? 'cm' : 'سم') : (lang === 'en' ? 'cm' : 'سم');
      const colorLabel = COLORS[item.color].label;
      
      let details = `${idx + 1}. ${typeLabel} - ${item.size}${sizeUnit} - ${colorLabel}`;
      
      if (item.hardware && item.hardware !== HardwareType.NONE) {
         // Find translation key for hardware
         const hardwareKey = Object.keys(HardwareType).find(key => HardwareType[key as keyof typeof HardwareType] === item.hardware);
         const hwTransKey = `hardware${hardwareKey?.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join('')}`.replace('Hardware', 'hardware'); 
         // Mapping "HANDLE_GOLD" -> "HandleGold" -> "hardwareHandleGold" is tricky with loose strings.
         // Let's just use manual map based on enum values for safety.
         let hwLabel = item.hardware;
         if (item.hardware === HardwareType.HANDLE_GOLD) hwLabel = t.hardwareHandleGold;
         if (item.hardware === HardwareType.HANDLE_BLACK) hwLabel = t.hardwareHandleBlack;
         if (item.hardware === HardwareType.KNOB_GOLD) hwLabel = t.hardwareKnobGold;
         if (item.hardware === HardwareType.KNOB_BLACK) hwLabel = t.hardwareKnobBlack;
         
         details += ` + ${hwLabel}`;
      }
      
      message += `${details}\n`;
    });

    message += `\n------------------\n`;
    message += lang === 'en' 
      ? `Total Length: ${tableLen}m\n`
      : `إجمالي الطول: ${tableLen} متر\n`;
    message += lang === 'en' 
      ? `Total Price: ${total} KD`
      : `السعر الإجمالي: ${total} د.ك`;

    const encoded = encodeURIComponent(message);
    window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${encoded}`, '_blank');
  };

  // --- Veo Logic ---
  const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onloadend = () => {
        setVeoImage(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const generateVideo = async () => {
    if (!veoImage) {
      alert(t.veoSelectImg);
      return;
    }

    try {
      const aiStudio = (window as any).aistudio;
      if (aiStudio && !await aiStudio.hasSelectedApiKey()) {
        await aiStudio.openSelectKey();
      }

      setVeoLoading(true);
      setVeoVideo(null);

      const ai = new GoogleGenAI({ apiKey: process.env.API_KEY });
      const base64Data = veoImage.split(',')[1];
      const mimeType = veoImage.split(';')[0].split(':')[1];

      let operation = await ai.models.generateVideos({
        model: 'veo-3.1-fast-generate-preview',
        prompt: veoPrompt || 'Cinematic camera movement',
        image: {
          imageBytes: base64Data,
          mimeType: mimeType
        },
        config: {
          numberOfVideos: 1,
          resolution: '720p',
          aspectRatio: veoRatio
        }
      });

      while (!operation.done) {
        await new Promise(resolve => setTimeout(resolve, 5000));
        operation = await ai.operations.getVideosOperation({ operation: operation });
      }

      const downloadLink = operation.response?.generatedVideos?.[0]?.video?.uri;
      if (downloadLink) {
        const response = await fetch(`${downloadLink}&key=${process.env.API_KEY}`);
        const blob = await response.blob();
        const videoUrl = URL.createObjectURL(blob);
        setVeoVideo(videoUrl);
      } else {
        throw new Error("No video URI returned");
      }

    } catch (error) {
      console.error("Video generation failed:", error);
      alert("Failed to generate video. Please try again.");
    } finally {
      setVeoLoading(false);
    }
  };

  return (
    <div className={`flex flex-col md:flex-row h-screen overflow-hidden ${isRTL ? 'font-ar' : ''}`} dir={isRTL ? 'rtl' : 'ltr'}>
      
      {/* LEFT PANEL: Controls */}
      <div className="w-full md:w-1/3 lg:w-1/4 bg-white border-r border-gray-200 flex flex-col shadow-xl z-20 h-2/5 md:h-full overflow-y-auto order-2 md:order-1">
        <div className="p-4 md:p-6 space-y-6">
          
          <div className="flex items-center justify-between md:hidden">
             <h2 className="text-xl font-bold text-gray-800">{t.title}</h2>
             <span className="text-xs bg-gray-100 px-2 py-1 rounded">{calculateTotal()} KD</span>
          </div>
          
          <div className="hidden md:flex items-center space-x-2 rtl:space-x-reverse mb-4">
            <button onClick={onBack} className="p-2 hover:bg-gray-100 rounded-full text-gray-500 transition-colors">
              <Undo2 size={20} />
            </button>
            <h2 className="text-2xl font-bold text-gray-800">{t.title}</h2>
          </div>

          {/* Type Selection */}
          <div className="space-y-3">
            <label className="text-sm font-semibold text-gray-500 uppercase tracking-wider">{lang === 'en' ? 'Item Type' : 'نوع القطعة'}</label>
            <div className="grid grid-cols-2 gap-3">
              <button 
                onClick={() => setSelectedType(ItemType.TABLE)}
                className={`p-3 rounded-xl border-2 transition-all flex items-center justify-center font-bold ${selectedType === ItemType.TABLE ? 'border-black bg-black text-white' : 'border-gray-200 text-gray-600 hover:border-gray-300'}`}
              >
                {t.tables}
              </button>
              <button 
                onClick={() => setSelectedType(ItemType.CABINET)}
                className={`p-3 rounded-xl border-2 transition-all flex items-center justify-center font-bold ${selectedType === ItemType.CABINET ? 'border-black bg-black text-white' : 'border-gray-200 text-gray-600 hover:border-gray-300'}`}
              >
                {t.cabinets}
              </button>
            </div>
          </div>

          {/* Size Selection */}
          <div className="space-y-3">
            <label className="text-sm font-semibold text-gray-500 uppercase tracking-wider">{t.size}</label>
            <div className="flex flex-wrap gap-2">
              {selectedType === ItemType.TABLE ? (
                ['100', '150', '200'].map((size) => (
                  <button
                    key={size}
                    onClick={() => setSelectedSize(size as TableSize)}
                    className={`px-4 py-2 rounded-lg border text-sm font-semibold transition-all ${selectedSize === size ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'}`}
                  >
                    {size} {t.cm}
                    <span className="block text-[10px] opacity-70 font-normal">{PRICING.tables[size as TableSize]} KD</span>
                  </button>
                ))
              ) : (
                ['40', '60'].map((size) => (
                  <button
                    key={size}
                    onClick={() => setSelectedSize(size as CabinetWidth)}
                    className={`px-4 py-2 rounded-lg border text-sm font-semibold transition-all ${selectedSize === size ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'}`}
                  >
                    {size} {t.cm}
                    <span className="block text-[10px] opacity-70 font-normal">{PRICING.cabinets[size as CabinetWidth]} KD</span>
                  </button>
                ))
              )}
            </div>
          </div>

          {/* Color Selection */}
          <div className="space-y-3">
            <label className="text-sm font-semibold text-gray-500 uppercase tracking-wider">{t.color}</label>
            <div className="grid grid-cols-4 gap-2">
              {Object.values(FurnitureColor).map((color) => (
                <button
                  key={color}
                  onClick={() => setSelectedColor(color)}
                  className={`relative w-full aspect-square rounded-full shadow-sm border-2 transition-transform hover:scale-105 ${selectedColor === color ? 'border-blue-500 ring-2 ring-blue-200' : 'border-transparent'}`}
                  title={COLORS[color].label}
                >
                  <div className={`w-full h-full rounded-full ${COLORS[color].tailwind}`} />
                </button>
              ))}
            </div>
            <div className="text-xs text-center text-gray-500 font-medium">
                {COLORS[selectedColor].label}
            </div>
          </div>

          {/* Hardware Selection (Cabinets Only) */}
          {selectedType === ItemType.CABINET && (
             <div className="space-y-3 animate-fade-in">
               <label className="text-sm font-semibold text-gray-500 uppercase tracking-wider">{t.hardware}</label>
               <div className="grid grid-cols-3 gap-2">
                 <button 
                    onClick={() => setSelectedHardware(HardwareType.NONE)}
                    className={`p-2 rounded-lg border text-xs font-medium text-center transition-all ${selectedHardware === HardwareType.NONE ? 'bg-gray-800 text-white border-gray-800' : 'text-gray-600 border-gray-200'}`}
                 >
                   {t.hardwareNone}
                 </button>
                 <button 
                    onClick={() => setSelectedHardware(HardwareType.HANDLE_GOLD)}
                    className={`p-2 rounded-lg border text-xs font-medium text-center transition-all ${selectedHardware === HardwareType.HANDLE_GOLD ? 'bg-yellow-50 text-yellow-700 border-yellow-400 ring-1 ring-yellow-400' : 'text-gray-600 border-gray-200'}`}
                 >
                   {t.hardwareHandleGold} (+{PRICING.hardware.handle_gold})
                 </button>
                 <button 
                    onClick={() => setSelectedHardware(HardwareType.HANDLE_BLACK)}
                    className={`p-2 rounded-lg border text-xs font-medium text-center transition-all ${selectedHardware === HardwareType.HANDLE_BLACK ? 'bg-gray-100 text-black border-gray-400 ring-1 ring-gray-400' : 'text-gray-600 border-gray-200'}`}
                 >
                   {t.hardwareHandleBlack} (+{PRICING.hardware.handle_black})
                 </button>
                 <button 
                    onClick={() => setSelectedHardware(HardwareType.KNOB_GOLD)}
                    className={`p-2 rounded-lg border text-xs font-medium text-center transition-all ${selectedHardware === HardwareType.KNOB_GOLD ? 'bg-yellow-50 text-yellow-700 border-yellow-400 ring-1 ring-yellow-400' : 'text-gray-600 border-gray-200'}`}
                 >
                   {t.hardwareKnobGold} (+{PRICING.hardware.knob_gold})
                 </button>
                 <button 
                    onClick={() => setSelectedHardware(HardwareType.KNOB_BLACK)}
                    className={`p-2 rounded-lg border text-xs font-medium text-center transition-all ${selectedHardware === HardwareType.KNOB_BLACK ? 'bg-gray-100 text-black border-gray-400 ring-1 ring-gray-400' : 'text-gray-600 border-gray-200'}`}
                 >
                   {t.hardwareKnobBlack} (+{PRICING.hardware.knob_black})
                 </button>
               </div>
               
               {/* Feature Badge */}
               <p className="text-xs text-yellow-600 flex items-center bg-yellow-50 p-2 rounded border border-yellow-100 mt-2">
                  ✨ {t.cabinetFeature}
               </p>
             </div>
          )}

          {/* Add Button */}
          <button 
            onClick={handleAddItem}
            className="w-full py-4 bg-black text-white rounded-xl font-bold text-lg hover:bg-gray-800 transition-colors shadow-lg flex items-center justify-center space-x-2 rtl:space-x-reverse"
          >
            <Plus size={20} />
            <span>{selectedType === ItemType.TABLE ? t.addTable : t.addCabinet}</span>
          </button>

          {/* AI Veo Button */}
          <button
            onClick={() => setShowVeo(true)}
            className="w-full py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-bold text-md hover:from-indigo-600 hover:to-purple-700 transition-all shadow-md flex items-center justify-center space-x-2 rtl:space-x-reverse"
          >
            <Video size={18} />
            <span>{t.veoBtn}</span>
          </button>

          <hr className="border-gray-100" />

          {/* Summary Section (Desktop) */}
          <div className="hidden md:block space-y-4">
             <div className="flex justify-between items-center text-lg font-bold">
               <span>{t.total}</span>
               <span>{calculateTotal()} KD</span>
             </div>
             <button 
                onClick={generateWhatsAppLink}
                disabled={items.length === 0}
                className={`w-full py-3 rounded-xl font-bold text-white flex items-center justify-center space-x-2 rtl:space-x-reverse shadow-md transition-all ${items.length === 0 ? 'bg-gray-300 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700 hover:shadow-green-200'}`}
             >
               <Share2 size={18} />
               <span>{t.orderWhatsapp}</span>
             </button>
          </div>

        </div>
      </div>

      {/* RIGHT PANEL: Wall Canvas */}
      <div className="w-full md:w-2/3 lg:w-3/4 bg-gray-100 relative overflow-hidden flex flex-col order-1 md:order-2 h-3/5 md:h-full">
        
        {/* Wall Controls (Color) */}
        <div className="absolute top-4 right-4 z-10 bg-white/90 backdrop-blur p-2 rounded-lg shadow-sm border border-gray-200 flex flex-col space-y-2">
           <label className="text-[10px] text-gray-500 uppercase font-bold text-center">{t.wallColor}</label>
           <div className="flex space-x-1 rtl:space-x-reverse">
             {['#f3f4f6', '#e5e7eb', '#d6d3d1', '#78716c', '#f0f9ff', '#fff1f2'].map(c => (
               <button 
                key={c}
                onClick={() => setWallColor(c)}
                className={`w-6 h-6 rounded-full border border-gray-300 shadow-inner ${wallColor === c ? 'ring-2 ring-black' : ''}`}
                style={{ backgroundColor: c }}
               />
             ))}
           </div>
        </div>

        {/* Back Button Mobile */}
        <div className="absolute top-4 left-4 z-10 md:hidden">
            <button onClick={onBack} className="p-2 bg-white/90 rounded-full shadow border border-gray-200">
               <Undo2 size={18} />
            </button>
        </div>

        {/* The Wall Area */}
        <div 
            className="flex-1 flex flex-col items-center justify-center relative shadow-inner transition-colors duration-500"
            style={{ backgroundColor: wallColor }}
        >
             <div className="absolute inset-0 opacity-5 pointer-events-none" 
                  style={{ backgroundImage: 'radial-gradient(circle, #000 1px, transparent 1px)', backgroundSize: '20px 20px' }}
             ></div>

             <div className="absolute bottom-0 w-full h-16 bg-[#e5e5e5] border-t border-gray-300"></div>
             <div className="absolute bottom-0 w-full h-4 bg-[#d4d4d4] border-t border-gray-300"></div>

             <div className="relative z-10 flex items-end justify-center px-4 md:px-20 w-full max-w-7xl h-[400px] mb-8">
                {items.length === 0 ? (
                  <div className="text-gray-400 text-center animate-pulse">
                    <ShoppingCart size={48} className="mx-auto mb-2 opacity-20" />
                    <p>{t.emptyWall}</p>
                  </div>
                ) : (
                  <>
                    {/* Items Container */}
                    <div className="flex items-end justify-center shadow-2xl bg-black/5 backdrop-blur-[1px] rounded-lg p-1 border-b-2 border-black/10">
                      {items.map((item, index) => (
                        <FurniturePiece 
                          key={item.id} 
                          index={index}
                          item={item} 
                          onRemove={handleRemoveItem}
                          onMove={handleMoveItem}
                          lang={lang}
                          isHovered={hoveredId === item.id}
                          setHovered={setHoveredId}
                          onDragStart={handleDragStart}
                          onDrop={handleDrop}
                        />
                      ))}
                    </div>
                    
                    {/* Drag Hint (only if items exist) */}
                    <div className="absolute -bottom-10 text-xs text-gray-400 uppercase tracking-widest font-bold opacity-50">
                        {t.dragHint}
                    </div>
                  </>
                )}
             </div>

             {items.length > 0 && (
                 <div className="absolute bottom-20 md:hidden z-10 bg-black/80 text-white px-4 py-2 rounded-full text-sm font-bold backdrop-blur">
                    {calculateTotal()} KD | {calculateTotalLength()}m
                 </div>
             )}
        </div>

        <div className="md:hidden absolute bottom-4 right-4 z-30">
             <button 
                onClick={generateWhatsAppLink}
                disabled={items.length === 0}
                className={`p-4 rounded-full text-white shadow-xl transition-all ${items.length === 0 ? 'bg-gray-400 hidden' : 'bg-green-600 animate-bounce'}`}
             >
               <Share2 size={24} />
             </button>
        </div>
      </div>

      {/* Veo Modal */}
      {showVeo && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            {/* Header */}
            <div className="p-4 border-b flex justify-between items-center bg-gray-50">
               <h3 className="text-lg font-bold flex items-center gap-2">
                 <Video className="text-purple-600" size={20}/>
                 {t.veoTitle}
               </h3>
               <button onClick={() => setShowVeo(false)} className="p-1 hover:bg-gray-200 rounded-full">
                 <X size={20} />
               </button>
            </div>

            {/* Body */}
            <div className="p-6 space-y-5 overflow-y-auto">
              
              {/* Image Upload */}
              <div className="space-y-2">
                 <label className="text-sm font-semibold text-gray-700">{t.veoUpload}</label>
                 <div className="relative border-2 border-dashed border-gray-300 rounded-xl p-4 hover:bg-gray-50 transition-colors text-center cursor-pointer">
                    <input type="file" accept="image/*" onChange={handleImageUpload} className="absolute inset-0 opacity-0 cursor-pointer" />
                    {veoImage ? (
                      <img src={veoImage} alt="Preview" className="h-32 mx-auto object-contain rounded-md shadow-sm" />
                    ) : (
                      <div className="flex flex-col items-center text-gray-400 py-4">
                        <Upload size={32} className="mb-2" />
                        <span className="text-xs">{t.veoUpload}</span>
                      </div>
                    )}
                 </div>
              </div>

              {/* Prompt */}
              <div className="space-y-2">
                <label className="text-sm font-semibold text-gray-700">{t.veoPrompt}</label>
                <input 
                  type="text" 
                  value={veoPrompt}
                  onChange={(e) => setVeoPrompt(e.target.value)}
                  placeholder={lang === 'en' ? "e.g., Cinematic pan of the room" : "مثال: تحرك سينمائي"}
                  className="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-purple-500 focus:outline-none"
                />
              </div>

              {/* Aspect Ratio */}
              <div className="space-y-2">
                <label className="text-sm font-semibold text-gray-700">{t.veoRatio}</label>
                <div className="grid grid-cols-2 gap-3">
                  <button 
                    onClick={() => setVeoRatio('16:9')}
                    className={`p-2 rounded-lg border text-sm ${veoRatio === '16:9' ? 'bg-purple-100 border-purple-500 text-purple-700' : 'border-gray-300 text-gray-600'}`}
                  >
                    16:9 (Landscape)
                  </button>
                  <button 
                    onClick={() => setVeoRatio('9:16')}
                    className={`p-2 rounded-lg border text-sm ${veoRatio === '9:16' ? 'bg-purple-100 border-purple-500 text-purple-700' : 'border-gray-300 text-gray-600'}`}
                  >
                    9:16 (Portrait)
                  </button>
                </div>
              </div>

              {/* Result Video */}
              {veoVideo && (
                <div className="mt-4 rounded-xl overflow-hidden shadow-lg border border-gray-200 bg-black">
                   <video src={veoVideo} controls className="w-full h-auto max-h-[300px]" autoPlay loop />
                </div>
              )}

            </div>

            {/* Footer */}
            <div className="p-4 border-t bg-gray-50 flex justify-end">
               <button 
                 onClick={generateVideo}
                 disabled={veoLoading || !veoImage}
                 className={`px-6 py-3 rounded-xl font-bold text-white flex items-center gap-2 shadow-lg transition-all ${veoLoading || !veoImage ? 'bg-gray-400 cursor-not-allowed' : 'bg-purple-600 hover:bg-purple-700'}`}
               >
                 {veoLoading ? (
                   <>
                     <Loader2 size={18} className="animate-spin" />
                     <span>{t.veoGenerating}</span>
                   </>
                 ) : (
                   <span>{t.veoGenerate}</span>
                 )}
               </button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
};

export default Designer;