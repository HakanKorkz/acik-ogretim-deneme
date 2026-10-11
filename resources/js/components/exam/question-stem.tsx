import { splitNegativeStem } from '@/lib/exam';

/**
 * Giriş metni (varsa) ve olumsuz kısmı vurgulanmış soru kökü.
 */
export default function QuestionStem({
    number,
    passage,
    text,
}: {
    number: number;
    passage: string | null;
    text: string;
}) {
    return (
        <div className="flex gap-2 text-[15px] leading-relaxed">
            <span className="font-bold">{number}.</span>
            <div className="flex-1">
                {passage && (
                    <p className="mb-3 whitespace-pre-line text-slate-800 dark:text-slate-200">
                        {passage}
                    </p>
                )}
                <p className="font-medium">
                    {splitNegativeStem(text).map((part, index) =>
                        part.emphasized ? (
                            <strong
                                key={index}
                                className="font-extrabold underline underline-offset-2"
                            >
                                {part.text}
                            </strong>
                        ) : (
                            <span key={index}>{part.text}</span>
                        ),
                    )}
                </p>
            </div>
        </div>
    );
}
