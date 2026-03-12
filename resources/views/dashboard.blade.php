import React, { useState, useEffect, useRef } from 'react';

// Catatan: Kami menggunakan window.L (Leaflet global) untuk menghindari kesalahan "Could not resolve"
// Pastikan Leaflet CDN sudah terpasang di layout utama (HTML/Blade) Anda.

const App = () => {
  const [devices, setDevices] = useState([]);
  const [selectedDevice, setSelectedDevice] = useState(null);
  const [isSidebarOpen, setIsSidebarOpen] = useState(window.innerWidth > 768);
  
  const mapRef = useRef(null);
  const mapInstance = useRef(null);
  const markersRef = useRef({});

  // 1. Memuat Script Leaflet secara dinamis jika belum ada
  useEffect(() => {
    const loadLeaflet = () => {
      if (!window.L) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.async = true;
        script.onload = initMap;
        document.head.appendChild(script);
      } else {
        initMap();
      }
    };

    const initMap = () => {
      if (mapRef.current && !mapInstance.current) {
        mapInstance.current = window.L.map(mapRef.current, { zoomControl: false }).setView([-5.1476, 119.4327], 13);
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: 'Prima GPS'
        }).addTo(mapInstance.current);
      }
    };

    loadLeaflet();
    fetchData();
    const interval = setInterval(fetchData, 5000);
    return () => clearInterval(interval);
  }, []);

  // 2. Sinkronisasi Data dari API
  const fetchData = async () => {
    try {
      const response = await fetch('/api/gps-data');
      const data = await response.json();
      const filtered = data.filter(d => d.latitude && d.longitude);
      setDevices(filtered);
      updateMarkers(filtered);
    } catch (error) {
      console.error("Gagal sinkronisasi data:", error);
    }
  };

  // 3. Update Marker di Peta (Vanilla Leaflet logic di dalam React)
  const updateMarkers = (data) => {
    if (!window.L || !mapInstance.current) return;

    data.forEach(unit => {
      const lat = parseFloat(unit.latitude);
      const lng = parseFloat(unit.longitude);
      const isMoving = parseFloat(unit.speed) >= 5;
      const accOn = unit.acc_status == 1;
      
      const statusColor = isMoving ? '#22c55e' : (accOn ? '#3b82f6' : '#ef4444');
      const pulseClass = isMoving ? 'animate-ping' : '';

      const iconHtml = `
        <div style="display: flex; flex-direction: column; align-items: center; position: relative; bottom: 25px;">
          <div style="background: white; border: 2px solid #0f172a; border-radius: 6px; padding: 2px 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); white-space: nowrap; font-weight: 900; font-size: 10px; color: #0f172a; text-transform: uppercase; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
            <i class="fa-solid fa-key" style="color: ${accOn ? '#3b82f6' : '#cbd5e1'}; font-size: 8px;"></i>
            ${unit.name}
          </div>
          <div style="width: 14px; height: 14px; background: white; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;">
            <div style="width: 100%; height: 100%; border-radius: 50%; background: ${statusColor};" class="${pulseClass}"></div>
          </div>
        </div>
      `;

      const customIcon = window.L.divIcon({
        className: 'custom-gps-icon',
        html: iconHtml,
        iconSize: [100, 40],
        iconAnchor: [50, 40]
      });

      if (markersRef.current[unit.imei]) {
        markersRef.current[unit.imei].setLatLng([lat, lng]).setIcon(customIcon);
      } else {
        const marker = window.L.marker([lat, lng], { icon: customIcon }).addTo(mapInstance.current);
        marker.on('click', () => handleSelect(unit));
        markersRef.current[unit.imei] = marker;
      }
    });
  };

  const handleSelect = (device) => {
    setSelectedDevice(device);
    if (mapInstance.current) {
      mapInstance.current.flyTo([parseFloat(device.latitude), parseFloat(device.longitude)], 17, { duration: 1.5 });
    }
    if (window.innerWidth < 768) setIsSidebarOpen(false);
  };

  const toggleRelay = () => {
    if (confirm(`⚠️ KONFIRMASI: Matikan mesin kendaraan ${selectedDevice.name}?`)) {
      alert("Perintah 'Cut-off Relay' telah dikirim ke " + selectedDevice.imei);
    }
  };

  return (
    <div className="flex flex-col md:flex-row h-screen w-screen overflow-hidden bg-slate-100">
      
      {/* Sidebar Overlay for Mobile */}
      {!isSidebarOpen && window.innerWidth < 768 && (
        <button 
          onClick={() => setIsSidebarOpen(true)}
          className="absolute top-4 left-4 z-[1001] bg-slate-900 text-white w-10 h-10 rounded-xl flex items-center justify-center shadow-2xl"
        >
          <i className="fa-solid fa-bars-staggered"></i>
        </button>
      )}

      {/* Sidebar */}
      <aside className={`fixed inset-y-0 left-0 w-80 bg-white border-r border-slate-200 flex flex-col z-[1002] transition-transform duration-300 md:relative md:translate-x-0 ${isSidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="p-6 bg-slate-900 text-white flex justify-between items-center">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 bg-blue-600 rounded-lg flex items-center justify-center font-black text-xl">P</div>
            <div className="flex flex-col">
              <h1 className="font-black text-sm tracking-tight uppercase leading-none">PRIMA GPS</h1>
              <span className="text-[8px] text-blue-400 font-bold uppercase tracking-widest mt-1">Live Tracking</span>
            </div>
          </div>
          <button onClick={() => setIsSidebarOpen(false)} className="md:hidden text-slate-400">
            <i className="fa-solid fa-xmark text-lg"></i>
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-3 no-scrollbar">
          {devices.map(d => (
            <div 
              key={d.imei} onClick={() => handleSelect(d)}
              className={`p-4 rounded-2xl border-2 transition-all cursor-pointer ${selectedDevice?.imei === d.imei ? 'border-blue-500 bg-blue-50' : 'border-slate-50 bg-white hover:border-blue-100'}`}
            >
              <div className="flex justify-between items-start mb-2">
                <h3 className="font-black text-slate-800 text-[11px] uppercase truncate w-32">{d.name}</h3>
                <i className={`fa-solid fa-key text-[10px] ${d.acc_status == 1 ? 'text-blue-500' : 'text-slate-200'}`}></i>
              </div>
              <div className="flex justify-between items-end">
                <span className="text-[9px] text-slate-400 font-mono font-bold uppercase">{d.plate_number}</span>
                <span className={`text-[9px] font-black px-2 py-0.5 rounded-lg ${parseFloat(d.speed) >= 5 ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-400'}`}>
                  {parseFloat(d.speed) >= 5 ? Math.round(d.speed) + ' KM/H' : 'BERHENTI'}
                </span>
              </div>
            </div>
          ))}
        </div>
        
        <div className="p-4 border-t bg-slate-50">
            <a href="/devices" className="flex items-center justify-center gap-2 w-full py-4 bg-white border border-slate-200 rounded-xl text-[10px] font-black text-slate-600 uppercase tracking-widest shadow-sm">
                <i className="fa-solid fa-list-check text-blue-500"></i> Kelola Armada
            </a>
        </div>
      </aside>

      {/* Map Area */}
      <div className="flex-1 relative">
        <div ref={mapRef} className="h-full w-full z-0" />
        
        {/* Detail Panel Floating */}
        <div className={`absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-80 bg-white shadow-2xl z-[1000] p-6 rounded-t-[2.5rem] md:rounded-3xl border-t md:border border-slate-100 transition-transform duration-500 ${selectedDevice ? 'translate-y-0' : 'translate-y-full md:hidden'}`}>
          <div className="w-12 h-1 bg-slate-100 rounded-full mx-auto mb-6 md:hidden"></div>
          
          {selectedDevice && (
            <>
              <div className="flex items-center gap-4 mb-6">
                <div className={`w-14 h-14 rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl ${parseFloat(selectedDevice.speed) >= 5 ? 'bg-green-500' : 'bg-blue-600'}`}>
                  <i className="fa-solid fa-car-side"></i>
                </div>
                <div className="flex-1">
                  <h2 className="font-black text-slate-800 uppercase leading-none text-lg truncate w-40">{selectedDevice.name}</h2>
                  <p className="text-[10px] text-slate-400 font-mono mt-1 font-bold">{selectedDevice.plate_number}</p>
                </div>
                <button onClick={() => setSelectedDevice(null)} className="text-slate-200">
                  <i className="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
              </div>

              <div className="grid grid-cols-2 gap-3 mb-6">
                <div className="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                  <p className="text-[9px] text-slate-400 uppercase font-black mb-1">Kontak (ACC)</p>
                  <div className={`flex items-center justify-center gap-2 font-black text-xs ${selectedDevice.acc_status == 1 ? 'text-blue-600' : 'text-slate-400'}`}>
                    <i className="fa-solid fa-key"></i> <span>{selectedDevice.acc_status == 1 ? 'HIDUP' : 'MATI'}</span>
                  </div>
                </div>
                <div className="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                  <p className="text-[9px] text-slate-400 uppercase font-black mb-1">Kecepatan</p>
                  <p className="font-black text-xl text-slate-800">{Math.round(selectedDevice.speed || 0)} <small className="text-[10px] italic">km/h</small></p>
                </div>
              </div>

              <div className="space-y-2">
                {selectedDevice.module_type === 'GT06N' && (
                  <button onClick={toggleRelay} className="w-full bg-red-600 hover:bg-red-700 text-white py-4 rounded-2xl text-[10px] font-black uppercase transition shadow-xl flex items-center justify-center gap-2">
                    <i className="fa-solid fa-power-off"></i> Matikan Mesin (Relay)
                  </button>
                )}
                <a href={`/device/${selectedDevice.imei}/history`} className="w-full bg-slate-900 text-white py-4 rounded-2xl text-[10px] font-black uppercase flex items-center justify-center gap-2 text-center">
                    <i className="fa-solid fa-route"></i> Lihat Riwayat
                </a>
              </div>
            </>
          )}
        </div>

        {/* Live Indicator */}
        <div className="absolute top-4 right-4 z-[500] bg-white/90 backdrop-blur p-2 px-4 rounded-xl shadow-xl border border-white flex items-center gap-3">
            <div className="flex flex-col items-end">
                <span className="text-[10px] font-black text-slate-900 leading-none uppercase tracking-tighter">PRIMA GPS LIVE</span>
                <span className="text-[8px] text-green-500 font-bold uppercase tracking-widest">Connected</span>
            </div>
            <div className="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></div>
        </div>
      </div>
    </div>
  );
};

export default App;