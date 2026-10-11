import QuestionStem from '@/components/exam/question-stem';
import { cn } from '@/lib/utils';

export interface ReviewItem {
    number: number;
    passage: string | null;
    question: string;
    isCorrect: boolean;
    selectedLabel: string | null;
    correctLabel: string | null;
    options: { label: string; text: string; isCorrect: boolean }[];
    source: string;
    isPastExam: boolean;
    sourcePage: number | null;
    bookUrl: string | null;
}

/**
 * Sonuç ekranında tek bir sorunun değerlendirmesi.
 */
export default function QuestionReview({ item }: { item: ReviewItem }) {
    const status =
        item.selectedLabel === null
            ? 'blank'
            : item.isCorrect
              ? 'correct'
              : 'wrong';

    return (
        <article
            className={cn(
                'rounded-2xl border bg-white p-5 shadow-sm dark:bg-slate-900',
                status === 'correct' &&
                    'border-emerald-200 dark:border-emerald-900/60',
                status === 'wrong' && 'border-rose-200 dark:border-rose-900/60',
                status === 'blank' && 'border-slate-200 dark:border-slate-800',
            )}
        >
            <QuestionStem
                number={item.number}
                passage={item.passage}
                text={item.question}
            />

            <ul className="mt-4 grid gap-2 sm:grid-cols-2">
                {item.options.map((option) => {
                    const isSelected = option.label === item.selectedLabel;

                    return (
                        <li
                            key={option.label}
                            className={cn(
                                'rounded-xl border px-3 py-2 text-sm',
                                option.isCorrect
                                    ? 'border-emerald-300 bg-emerald-50 font-medium text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100'
                                    : isSelected
                                      ? 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100'
                                      : 'border-slate-200 dark:border-slate-700',
                            )}
                        >
                            <span className="font-bold">{option.label})</span>{' '}
                            {option.text}
                            {isSelected && (
                                <span className="ml-1 text-xs opacity-70">
                                    (cevabınız)
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>

            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                <span>
                    {status === 'blank'
                        ? 'Boş bırakıldı'
                        : status === 'correct'
                          ? 'Doğru'
                          : 'Yanlış'}{' '}
                    · Doğru cevap: <strong>{item.correctLabel}</strong>
                </span>
                <span>{item.source}</span>
                {item.sourcePage !== null &&
                    (item.bookUrl ? (
                        <a
                            href={item.bookUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            Kitapta s. {item.sourcePage} ↗
                        </a>
                    ) : (
                        <span>Kitapta s. {item.sourcePage}</span>
                    ))}
            </div>
        </article>
    );
}
