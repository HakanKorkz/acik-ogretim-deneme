/**
 * Gerçek AÖL kitapçıklarında olumsuz soru kökleri kalın ve altı çizili
 * basılır ("… hangisi değildir?"). Kökü bu parçalara ayırır.
 */
const NEGATIVE_PATTERN =
    /(değildir|değiller|yoktur|yanlıştır|ulaşılamaz|söylenemez|olamaz|gösterilemez|beklenemez|sayılamaz|bulunmaz|görülmez|dışta kalır|yer almaz|yapılmamıştır|değinilmemiştir|çıkarılamaz|kullanılmamıştır)/giu;

export interface StemPart {
    text: string;
    emphasized: boolean;
}

export function splitNegativeStem(stem: string): StemPart[] {
    return stem
        .split(NEGATIVE_PATTERN)
        .filter((part) => part !== '')
        .map((part) => ({
            text: part,
            emphasized: new RegExp(`^${NEGATIVE_PATTERN.source}$`, 'iu').test(
                part,
            ),
        }));
}

/**
 * Şıkların hepsi kısaysa kitapçıktaki gibi 2×2 dizilir.
 */
export function hasShortOptions(options: { text: string }[]): boolean {
    return options.every((option) => option.text.length <= 28);
}

export function formatDuration(totalSeconds: number): string {
    const safe = Math.max(0, totalSeconds);
    const minutes = Math.floor(safe / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (safe % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
}
