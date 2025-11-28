import React, { useState } from 'react';
import { Language } from './types';
import { TRANSLATIONS } from './constants';
import Designer from './components/Designer';
import { Globe } from 'lucide-react';

const App: React.FC = () => {
  const [lang, setLang] = useState<Language>('ar');
  const [view, setView] = useState<'landing' | 'designer'>('landing');

  const t = TRANSLATIONS[lang];
  const isRTL = lang === 'ar';

  const toggleLang = () => {
    setLang(prev => prev === 'ar' ? 'en' : 'ar');
  };

  // Landing Page Component (Internal to avoid too many files, as per strict constraints)
  const LandingPage = () => (
    <div className={`relative min-h-screen flex flex-col overflow-hidden font-sans ${isRTL ? 'font-ar' : ''}`} dir={isRTL ? 'rtl' : 'ltr'}>
      
      {/* Background with Parallax Effect */}
      <div 
        className="absolute inset-0 z-0 bg-cover bg-center bg-no-repeat transform scale-105 transition-transform duration-[20s] hover:scale-110 ease-linear"
        style={{ 
          backgroundImage: 'url("https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1974&q=80")',
          /* Using Unsplash placeholder as requested "Real Room" vibe since local files aren't available to the runner */
        }}
      >
        <div className="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
      </div>

      {/* Header / Nav */}
      <nav className="relative z-10 flex justify-between items-center p-6 md:p-10 text-white">
        <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Marcos Home</h1>
        <button 
          onClick={toggleLang}
          className="flex items-center space-x-2 rtl:space-x-reverse bg-white/10 hover:bg-white/20 px-4 py-2 rounded-full backdrop-blur-md transition-all border border-white/20"
        >
          <Globe size={18} />
          <span className="font-semibold text-sm">{t.lang}</span>
        </button>
      </nav>

      {/* Hero Content */}
      <main className="relative z-10 flex-1 flex flex-col items-center justify-center text-center text-white px-4">
        <div className="space-y-6 max-w-3xl animate-fade-in-up">
          <h2 className="text-5xl md:text-7xl font-extrabold tracking-tight leading-tight drop-shadow-lg">
            {t.slogan}
          </h2>
          <p className="text-lg md:text-2xl text-gray-200 font-light max-w-2xl mx-auto drop-shadow-md opacity-90">
             {lang === 'en' ? 'Customize your TV units and cabinets with precision.' : 'خصص وحدات التلفاز والخزائن بدقة عالية.'}
          </p>
          
          <div className="pt-8">
            <button 
              onClick={() => setView('designer')}
              className="group relative inline-flex items-center justify-center px-8 py-4 text-lg font-bold text-black transition-all duration-200 bg-white font-pj rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 hover:bg-gray-100 hover:scale-105 shadow-2xl"
            >
              {t.start}
              <div className="absolute -inset-3 rounded-full bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity duration-200 blur-lg"></div>
            </button>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="relative z-10 p-6 text-center text-gray-400 text-sm">
        &copy; {new Date().getFullYear()} Marcos Home. All rights reserved.
      </footer>
    </div>
  );

  return (
    <>
      {view === 'landing' ? <LandingPage /> : <Designer lang={lang} onBack={() => setView('landing')} />}
    </>
  );
};

export default App;
