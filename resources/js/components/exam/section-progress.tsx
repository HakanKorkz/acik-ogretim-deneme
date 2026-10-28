import { cn } from '@/lib/utils';

export interface ExamSummary {
    id: string;
    level: string;
    levelLabel: string;
    pool: string;
    resultMode: string;
    sectionMinutes: number;
    passScore: number;
    sections: { position: number; course: string; isCompleted: boolean }[];
}

/**
 * Sınavdaki derslerin sırası ve hangisinde olunduğu.
 */
export default function SectionProgress({
    exam,
    currentPosition,
}: {
    exam: ExamSummary;
    currentPosition: number | null;
}) {
    if (exam.sections.length < 2) {
        return null;
    }

    return (
        <ol className="flex flex-wrap gap-2 text-xs">
            {exam.sections.map((section) => (
                <li
                    key={section.position}
                    className={cn(
                        'rounded-full px-3 py-1 font-medium',
                        section.position === currentPosition
                            ? 'bg-study-fill text-study-on-fill'
                            : section.isCompleted
                              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                              : 'bg-study-tint text-study-muted',
                    )}
                >
                    {section.position}. {section.course}
                    {section.isCompleted && ' ✓'}
                </li>
            ))}
        </ol>
    );
}
