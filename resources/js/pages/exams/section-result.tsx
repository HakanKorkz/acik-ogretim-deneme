import CopyJsonButton from '@/components/exam/copy-json-button';
import CourseResultCard, {
    type CourseResult,
} from '@/components/exam/course-result-card';
import QuestionReview, {
    type ReviewItem,
} from '@/components/exam/question-review';
import SectionProgress, {
    type ExamSummary,
} from '@/components/exam/section-progress';
import AppLayout from '@/layouts/app-layout';
import { buildExamResultJson } from '@/lib/exam';
import { result as examResult, show } from '@/routes/exams';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    exam: ExamSummary;
    result: CourseResult & { review: ReviewItem[] };
    hasNextSection: boolean;
}

function SectionResult({ exam, result, hasNextSection }: Props) {
    const [showReview, setShowReview] = useState(false);
    const next = exam.sections.find((section) => !section.isCompleted);

    return (
        <>
            <Head title={`${result.course} - Sonuç`} />

            <SectionProgress exam={exam} currentPosition={null} />

            <div className="mt-6 grid gap-6">
                <CourseResultCard result={result} passScore={exam.passScore} />

                <div className="flex flex-wrap gap-3">
                    {hasNextSection && next ? (
                        <Link
                            href={show.url(exam.id)}
                            className="rounded-sm bg-study-fill px-5 py-2.5 text-sm font-semibold text-study-on-fill transition hover:opacity-90"
                        >
                            Sıradaki ders: {next.course} →
                        </Link>
                    ) : (
                        <Link
                            href={examResult.url(exam.id)}
                            className="rounded-sm bg-study-fill px-5 py-2.5 text-sm font-semibold text-study-on-fill transition hover:opacity-90"
                        >
                            Toplu sonucu gör →
                        </Link>
                    )}
                    <button
                        type="button"
                        onClick={() => setShowReview((value) => !value)}
                        className="rounded-sm border border-study-rule px-5 py-2.5 text-sm font-semibold text-study-muted transition hover:bg-study-tint"
                    >
                        {showReview
                            ? 'Cevap anahtarını gizle'
                            : 'Cevap anahtarı ve çözümler'}
                    </button>
                    <CopyJsonButton
                        build={() => buildExamResultJson(exam, [result])}
                    />
                </div>

                {showReview && (
                    <div className="grid gap-4">
                        {result.review.map((item) => (
                            <QuestionReview key={item.number} item={item} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

SectionResult.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;

export default SectionResult;
