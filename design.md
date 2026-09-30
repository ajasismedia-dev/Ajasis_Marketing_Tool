# Ajasis CMS Tasarım Dili - Marketing Tool

Bu doküman Ajasis Marketing Tool projesinin ana tasarım referansıdır.

## Temel Kurallar
1. **Ana Arka Plan**: `#0d0d0d` (Koyu premium görünüm)
2. **Primary Renk**: `#c1ff00` (Neon lime)
3. **Kart Yapısı**: Glassmorphism
   - Arka plan: `rgba(255, 255, 255, 0.03)`
   - Kenarlık: `1px solid rgba(255, 255, 255, 0.08)`
   - Backdrop filter: `blur(10px)`
4. **Metin Renkleri**:
   - Ana Metin: `#ffffff`
   - İkincil Metin: `#a0a0a0`
5. **Sidebar**: Sabit, sol tarafta, daraltılabilir (ileride), menü hover animasyonları.
6. **Topbar**: Sayfa başlığı ve kullanıcı bilgilerini içerir.
7. **İkonlar**: Lucide ikon seti kullanılacak (`<script src="https://unpkg.com/lucide@latest"></script>`). Boyutlandırma `w-5 h-5` veya `w-6 h-6` şeklinde olacak.
8. **Form & Input**:
   - Arka plan: `rgba(255, 255, 255, 0.05)`
   - Focus border: `#c1ff00`
   - Padding: `0.75rem 1rem`
   - Radius: `8px` veya `12px`
9. **Spacing & Radius**:
   - Genel border-radius: `12px`
   - Kart padding: `1.5rem`
10. **Hover Animasyonları**: Mikro animasyonlar kullanılacak (`transition: all 0.3s ease`). Kartlar hover'da hafif yukarı kalkabilir (`transform: translateY(-2px)`).
11. **Marka**: Logo metni "ajasis marketing" (kalın ve ince tipografi kombinasyonu).
