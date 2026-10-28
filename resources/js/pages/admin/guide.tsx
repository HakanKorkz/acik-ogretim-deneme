import AdminLayout from '@/layouts/admin-layout';
import {
    index as coursesIndex,
    create as courseCreate,
} from '@/routes/admin/courses';
import { index as pastExamsIndex } from '@/routes/admin/past-exams';
import { edit as profileEdit } from '@/routes/admin/profile';
import { index as questionExportIndex } from '@/routes/admin/questions/export';
import { edit as aiSettingsEdit } from '@/routes/admin/settings/ai';
import { edit as mediaSettingsEdit } from '@/routes/admin/settings/media';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    exam: {
        questionsPerCourse: number;
        questionsPerExam: number;
        sectionMinutes: number;
        maxCourses: number;
        passScore: number;
        uploadMaxMegabytes: number;
    };
}

interface GuideSection {
    id: string;
    title: string;
    description: string;
    steps: string[];
    notes: string[];
    link: { label: string; href: string };
}

function normalize(text: string): string {
    return text
        .toLocaleLowerCase('tr-TR')
        .normalize('NFD')
        .replace(/\p{M}/gu, '');
}

function Guide({ exam }: Props) {
    const [search, setSearch] = useState('');
    const sections: GuideSection[] = [
        {
            id: 'baslangic',
            title: 'İlk dersinizi yayına alın',
            description:
                'Bir dersin katalogda bulunması, öğrencilerin sınava girebileceği anlamına gelmez. Metni hazırlayın, soruları üretip kontrol edin ve dersi yayımlayın.',
            steps: [
                'Yapay Zekâ sayfasında sağlayıcıyı, API anahtarını ve kullanacağınız modelleri ayarlayın. Kaydedip bağlantıyı test edin.',
                'Dersler sayfasında lise veya ortaokul kademesini seçin. Gerekiyorsa MEB kataloğunu eşitleyin.',
                'Dersin sorumluluk aralığını kontrol edin ve soruları üretin. Kitap metni hazır değilse önce otomatik hazırlanır.',
                'Dersin Sorular bağlantısından mevcut soruları ve doğru cevapları inceleyin; hataları Düzenle ekranından düzeltin. Kitap metni bağlantısından kaynak metni kontrol edin.',
                'İçerik uygunsa Dersler sayfasındaki Yayınla düğmesini kullanın. Siteyi görüntüle üzerinden öğrenci tarafında da kontrol edin.',
            ],
            notes: [
                'Metin hazırlama, soru üretme ve kitapçık işleme arka planda çalışır. Sayfayı kapatmanız işlemi iptal etmez; kuyruk işçisinin sunucuda çalışması gerekir.',
            ],
            link: { label: 'Derslere git', href: coursesIndex.url() },
        },
        {
            id: 'dersler',
            title: 'Ders kataloğu ve sorumluluk aralığı',
            description:
                'MEB kataloğu ders adlarını, kitap bağlantılarını ve varsa sorumlu olunan sayfaları getirir. Katalog eşitleme tek başına bütün kitapları indirmez veya soru üretmez.',
            steps: [
                'Dersler sayfasında kademe ve arama alanıyla dersi bulun. Sayfa başına 10, 25, 50 veya 100 ders seçebilir; Ders, Soru ve Yayın başlıklarından sıralayabilirsiniz. Arama ve sıralama tüm sonuçlara uygulanır.',
                'Düzenle ekranında başlangıç ve bitiş sayfasını kontrol edin. Kitabın yalnızca ilgili derse ait bölümünü kullanın.',
                'Basılı sayfa ile PDF sayfası uyuşmuyorsa sayfa kaydırmasını düzeltin. İlk ve son sayfa önizlemelerini kontrol edin.',
                'Kitap güncellendi uyarısında kaynak kitabı inceleyin; gerekiyorsa metni yeniden hazırlayıp soruları yeniden üretin.',
            ],
            notes: [
                'MEB kitapları kalıcı olarak barındırılmaz: PDF geçici indirilir, metni sayfa sayfa saklanır. Sonuç ekranındaki kitap bağlantısı kaynak PDF’i açar.',
            ],
            link: { label: 'Ders kataloğunu aç', href: coursesIndex.url() },
        },
        {
            id: 'pdf',
            title: 'PDF ile ders ekleme ve kitap değiştirme',
            description:
                'MEB kataloğunda olmayan kitapları PDF yükleyerek ekleyebilirsiniz.',
            steps: [
                'PDF ile ders ekle ekranında kademe, ders bilgileri, dosya ve depolama hedefini seçin.',
                'Dersi kaydettikten sonra metin ve sorumluluk aralığını kontrol ederek soru üretin.',
                'Yüklenmiş kitabı değiştirmek için dersin düzenleme ekranındaki Kitabı değiştir alanını kullanın. Değişiklikten sonra yeni kitabın metnini ve soru bankasını hazırlayın.',
            ],
            notes: [
                `Panelin dosya üst sınırı ${exam.uploadMaxMegabytes} MB. Sunucunun yükleme sınırı daha düşükse dosya bu sınıra ulaşmadan reddedilebilir.`,
                'Cloudinary kullanılamıyorsa veya dosya Cloudinary için ayarlanan sınırı aşıyorsa uygulama nedeni bildirerek sunucuda saklamaya geçer.',
            ],
            link: { label: 'PDF ile ders ekle', href: courseCreate.url() },
        },
        {
            id: 'soru-uretimi',
            title: 'Soru üretme, yenileme ve kalite kontrolü',
            description:
                'Yapay zekâ dersin sorumlu olduğu kitap metninden soru üretir. Varsa çıkmış sorular üslup örneği olarak kullanılır.',
            steps: [
                `Dersler sayfasında Soruları üret işlemini başlatın. Mevcut soru bankası hedefi ders başına ${exam.questionsPerCourse} sorudur; kalite denetimleri nedeniyle daha az soru oluşabilir.`,
                'İşlem durumunu ders listesinden izleyin. Aynı ders için devam eden üretim bitmeden tekrar başlatmayın.',
                'Soru kökünün anlaşılır, öncüllerin eksiksiz ve dört şıktan yalnızca birinin doğru olduğunu kontrol edin. Kaynak sayfayı açarak cevapla karşılaştırın.',
                'Soruları yeniden üretmek mevcut üretilmiş bankayı yeniler. Kullanılmış sorular geçmiş sınavlar korunsun diye arşivlenir; çıkmış sorular bu işlemle yenilenmez.',
            ],
            notes: [
                'Yapay zekâ kullanılamayan bölümlerde yerel, kural tabanlı üretici devreye girebilir. Soru sayısının artması tek başına yapay zekâ çağrısının başarılı olduğunu göstermez.',
                'Dersler listesindeki Sorular bağlantısıyla soru bankasını inceleyin. Düzenle ekranından giriş metni, soru kökü, dört şık, doğru cevap ve kaynak sayfasını değiştirebilirsiniz. Kaydettiğiniz sürüm yeni sınavlarda kullanılır; önceki sınavların içeriği korunur. Aynı soruyu başka bir sekmede değiştirdiyseniz güncel sürümü yeniden açın. Kitap metni bağlantısı çıkarılmış metni sorumlu aralıkta veya bütün kitapta sayfa sayfa gösterir; arama yapabilirsiniz. Üretilen içeriği kaynakla karşılaştırarak doğrulayın.',
            ],
            link: { label: 'Üretim durumlarını gör', href: coursesIndex.url() },
        },
        {
            id: 'yayin',
            title: 'Yayınla ve yayından kaldır',
            description:
                'Öğrenci tarafında yalnızca yayındaki dersler listelenir. Sorular hazır olsa bile pasif ders görünmez.',
            steps: [
                'Dersler sayfasında soru sayısını ve içeriği kontrol edin.',
                'Dersi görünür yapmak için Yayınla, listeden kaldırmak için Yayından kaldır düğmesine basın. Sorusu olmayan dersin yayınlama düğmesi kapalıdır.',
                'Öğrenci tarafında doğru kademeyi ve soru türünü seçerek sınavın açıldığını doğrulayın.',
            ],
            notes: [
                'Yayın durumu ile soru türü farklıdır. Yayındaki bir derste yapay zekâ soruları bulunabilir, fakat henüz çıkmış soru bulunmayabilir.',
            ],
            link: {
                label: 'Yayın durumlarını yönet',
                href: coursesIndex.url(),
            },
        },
        {
            id: 'cikmis-sorular',
            title: 'Çıkmış soru arşivi ve yeniden işleme',
            description:
                'Kitapçık listesini eşitlemek yalnızca arşiv kayıtlarını getirir. Soruları bankaya almak için kitapçığı ayrıca işleyin.',
            steps: [
                'Çıkmış Sorular sayfasında listeyi eşitleyin. Kitapçıkları başlık veya dönemle arayın, duruma göre süzün ve sayfa düğmeleriyle ilgili oturumu bulun. Başlıklardan sıralayabilir, sayfa başına kayıt sayısını değiştirebilirsiniz.',
                'Aktarmak istediğiniz dersleri yayına alın. Kitapçığı işleme, yalnızca yayındaki derslerin uygun bölümlerini otomatik aktarır.',
                'İçe aktar veya Yeniden işle işlemini başlatın. Bölümler düğmesiyle o kitapçığın derslerini açıp durum ve aktarılan soru sayılarını inceleyin. Bölümler yalnızca açıldığında, 25’er kayıtla yüklenir.',
                'Ders önceki işlemde pasif olduğu için atlandıysa, dersi yayımladıktan sonra kitapçığı yeniden işleyin. Uygun bölümler için tek bölüm yeniden denemesi de kullanılabilir.',
                'Aktarılan soru metinlerini kaynak kitapçıkla, doğru şıkları da kitapçığın cevap anahtarıyla karşılaştırın.',
            ],
            notes: [
                'Kitapçığın Tamamlandı görünmesi bütün bölümlerin aktarıldığını göstermez. Atlandı ve Başarısız bölümlerini ayrıca inceleyin.',
                'Ders eşleşmesi veya okunabilir cevap anahtarı yoksa aktarım yapılamaz. Görsel gerektiren ya da eksik okunan sorular atlanır; doğru cevaplar tahmin edilmez.',
                'Eksiksiz ve sıralı okunabilen metin soruları doğrudan aktarılır. Diğer bölümler için yapay zekâ bağlantısı, tüm kitapçıklar için sunucuda pdftotext gerekir. Yerel soru üreticisi çıkmış soru üretmez.',
            ],
            link: { label: 'Çıkmış soruları aç', href: pastExamsIndex.url() },
        },
        {
            id: 'yapay-zeka',
            title: 'Yapay zekâ: sağlayıcı, modeller ve sistem promptu',
            description:
                'OpenRouter, OpenAI veya Anthropic seçin. API anahtarı seçtiğiniz sağlayıcıya ait olmalıdır.',
            steps: [
                'Sağlayıcıyı seçip anahtarını girin. Mevcut anahtarı korumak için alanı boş bırakın; kaldırmak için silme seçeneğini kullanın.',
                'Modelleri getir düğmesiyle listeyi alın. Model seçip Ekle ile deneme sırasına ekleyin.',
                'Modelleri sürükleyerek sıralayın. En üstteki önce denenir; hata veya geçersiz çıktı durumunda sıradakine geçilir.',
                'Sistem promptunu soru üretim yönergelerini değiştirmek için düzenleyin. Varsayılana dön ile kod içindeki yönergeye dönebilirsiniz. Bu alan çıkmış soru ayrıştırıcısının yönergesini değiştirmez.',
                'Ayarları kaydedin ve bağlantıyı test edin. Sonrasında küçük bir ders üretimiyle gerçek çıktıyı kontrol edin.',
            ],
            notes: [
                'Model listesinin gelmesi, listedeki her modelin hesabınızda kullanılabildiğini veya soru üretmeye uygun olduğunu garanti etmez. Anahtarlar şifreli saklanır.',
                'Ücretsiz modellerde kota ve yoğunluk sınırları olabilir. Tekrarlayan hatalarda sağlayıcının kotasını kontrol edin ve hesabınızda erişilebilir bir modeli sıraya ekleyin. Liste alınamadığında önerilen modeller gösterilebilir.',
            ],
            link: {
                label: 'Yapay zekâ ayarlarını aç',
                href: aiSettingsEdit.url(),
            },
        },
        {
            id: 'depolama',
            title: 'Depolama ve Cloudinary',
            description:
                'Depolama sayfası, panelden yüklenen PDF’lerin nerede tutulacağını belirler.',
            steps: [
                'Sunucuda saklamak için yerel hedefi kullanın. Cloudinary kullanacaksanız hesap bilgilerini girip kaydedin.',
                'Cloudinary hesabında Settings → Security → Allow delivery of PDF and ZIP files ayarını açın.',
                'Önce küçük bir PDF yükleyip dosyanın açıldığını ve metninin hazırlanabildiğini kontrol edin.',
            ],
            notes: [
                'PDF teslimi kapalıysa Cloudinary’ye yükleme başarılı görünse bile dosyayı açmak veya metnini indirmek 401 hatası verebilir. Cloudinary dosya sınırı paneldeki ayardan ve hesabınızın koşullarından etkilenir.',
            ],
            link: {
                label: 'Depolama ayarlarını aç',
                href: mediaSettingsEdit.url(),
            },
        },
        {
            id: 'sinav',
            title: 'Öğrencinin sınav deneyimi ve JSON kopyalama',
            description: `Öğrenci hesap açmadan kademesini ve en fazla ${exam.maxCourses} ders seçer. Ders başına hedef ${exam.questionsPerExam} soru, süre ${exam.sectionMinutes} dakika, geçme puanı ${exam.passScore}’dir. Havuzda daha az soru varsa sınav daha az soruyla oluşabilir.`,
            steps: [
                'Çıkmış, yapay zekâ destekli veya karışık soru türünü seçin. Sonuçların ders sonunda mı, sınavın tamamında mı gösterileceğini belirleyin.',
                'Yönergeleri okuyup BAŞLAYINIZ ile sınava girin. Dersler sırayla açılır; cevaplar işaretlendikçe kaydedilir ve dersin süresi dolunca bölüm teslim edilir.',
                'Sonuç ekranında doğru, yanlış, boş, puan ve cevap anahtarını inceleyin. Kitap kaynaklı sorularda kaynak sayfa bağlantısını kullanın.',
                'JSON kopyala ile ders, soru metni, şıklar, işaretlenen ve doğru cevap, sonuç ve kaynak bilgilerini panoya alın.',
            ],
            notes: [
                'Tekrar sınavında daha önce görülmeyen sorulara öncelik verilir ve şık sırası karıştırılır. Aynı tarayıcıdaki kimlik çerezi silinirse görülmüş soru takibi sıfırlanabilir.',
            ],
            link: { label: 'Dersleri kontrol et', href: coursesIndex.url() },
        },
        {
            id: 'excel',
            title: 'Soruları Excel’e dışa aktarma',
            description:
                'Soruları Dışa Aktar sayfasından model eğitimi için bir .xlsx dosyası indirebilirsiniz. Bir soru bir satırdır; giriş metni ve öncüller soru hücresinde korunur.',
            steps: [
                'Kademe, ders, soru kaynağı ve dersin yayın durumunu seçin. Boş bırakılan filtreler tüm uygun kayıtları kapsar.',
                'İsterseniz Kaynak bilgilerini de ekle seçeneğini açın. Soru kimliği ve kaynak bilgileri, eğitim ve test kümelerinin takibini kolaylaştırır.',
                'Excel dosyasını indir düğmesine basın. Sütunlar ders, konu, zorluk, soru, A, B, C, D ve cevap şeklindedir.',
                'Eğitime başlamadan içeriğin doğruluğunu ve tekrarları inceleyin. Aynı veya çok benzer soruları eğitim ve test kümelerine birlikte koymayın.',
            ],
            notes: [
                'Konu ve zorluk mevcut sorularda kayıtlı değildir; Excel’de boş bırakılır. Arşivlenmiş, eksik şıklı veya doğru cevap işaretlemesi tutarsız sorular aktarılmaz.',
                'Biçim kontrolünden geçen bir soru yine de içerik hatası taşıyabilir. Excel hücre sınırını aşan metinler kırpılmaz; veri kaybı yerine açıklayıcı hata gösterilir.',
            ],
            link: {
                label: 'Soruları dışa aktar',
                href: questionExportIndex.url(),
            },
        },
        {
            id: 'sorun-giderme',
            title: 'Sık karşılaşılan sorunlar',
            description:
                'Bir işlem beklediğiniz gibi sonuçlanmadığında aşağıdaki sırayla kontrol edin.',
            steps: [
                'Ders görünmüyor: doğru kademede olduğunuzu, dersin yayında bulunduğunu ve seçilen soru türünde sorusu olduğunu kontrol edin.',
                'İşlem sırada kalıyor: sunucudaki kuyruk işçisini kontrol ettirin. Kuyrukta olmak işlemin çalıştığı anlamına gelmez.',
                'Yapay zekâ yanıt vermiyor: kayıtlı anahtarı, sağlayıcı kotasını ve model erişimini kontrol edin. Bağlantı testi ve gerçek üretim farklı boyutta istekler gönderir.',
                'Çıkmış sorular sıfır: kitapçığın bölüm ayrıntılarına bakın. Ders pasifliği, eşleşme veya cevap anahtarı sorunu nedeniyle bölüm atlanmış olabilir.',
                'Kaynak sayfa yanlış: dersin sorumluluk aralığını ve basılı sayfa/PDF kaydırmasını kontrol edin.',
                'JSON kopyalanmıyor: tarayıcının pano iznini kontrol edin. HTTPS veya localhost üzerinden tekrar deneyin.',
            ],
            notes: [
                'Profil sayfasından ad, e-posta ve şifrenizi güncelleyebilirsiniz. Yeni yönetici hesabı oluşturmak için sunucu yöneticisinin admin:create komutunu çalıştırması gerekir.',
            ],
            link: { label: 'Profil ayarlarını aç', href: profileEdit.url() },
        },
    ];
    const query = normalize(search.trim());
    const visibleSections = sections.filter((section) =>
        normalize(
            [
                section.title,
                section.description,
                ...section.steps,
                ...section.notes,
            ].join(' '),
        ).includes(query),
    );

    return (
        <>
            <Head title="Kullanım Rehberi" />
            <header className="mb-8">
                <p className="text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                    Panel wikisi
                </p>
                <h1 className="mt-2 text-2xl font-bold tracking-tight">
                    Kullanım Rehberi
                </h1>
                <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                    Ders eklemekten sınavı yayımlamaya kadar günlük yönetim
                    adımları. İlk kez kullanıyorsanız ilk dersinizi yayına alma
                    bölümüyle başlayın.
                </p>
            </header>
            <div className="grid items-start gap-6 xl:grid-cols-[13rem_minmax(0,1fr)]">
                <aside className="rounded-2xl border border-slate-200 bg-white p-4 xl:sticky xl:top-6 dark:border-slate-800 dark:bg-slate-900">
                    <label
                        htmlFor="guide-search"
                        className="text-sm font-semibold"
                    >
                        Rehberde ara
                    </label>
                    <input
                        id="guide-search"
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Örn. soru üretme"
                        className="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950"
                    />
                    <nav
                        aria-label="Rehber içindekiler"
                        className="mt-4 flex flex-col gap-1"
                    >
                        {visibleSections.map((section) => (
                            <a
                                key={section.id}
                                href={`#${section.id}`}
                                className="rounded-lg px-2 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-indigo-300"
                            >
                                {section.title}
                            </a>
                        ))}
                    </nav>
                </aside>
                <div className="min-w-0 space-y-6">
                    <p
                        role="status"
                        className="text-sm text-slate-500 dark:text-slate-400"
                    >
                        {query
                            ? `${visibleSections.length} bölüm bulundu.`
                            : 'İçindekilerden bir konuya geçebilir veya rehberde arama yapabilirsiniz.'}
                    </p>
                    {visibleSections.length === 0 && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                            <p>Bu aramayla eşleşen bölüm bulunamadı.</p>
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="mt-3 text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                Aramayı temizle
                            </button>
                        </div>
                    )}
                    {visibleSections.map((section) => (
                        <section
                            key={section.id}
                            id={section.id}
                            aria-labelledby={`${section.id}-title`}
                            className="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <h2
                                id={`${section.id}-title`}
                                className="text-lg font-semibold"
                            >
                                {section.title}
                            </h2>
                            <p className="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">
                                {section.description}
                            </p>
                            <ol className="mt-5 list-decimal space-y-3 pl-5 text-sm leading-7 marker:font-semibold marker:text-indigo-600 dark:marker:text-indigo-400">
                                {section.steps.map((step) => (
                                    <li key={step} className="pl-1">
                                        {step}
                                    </li>
                                ))}
                            </ol>
                            <div className="mt-5 space-y-3 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600 dark:bg-slate-950 dark:text-slate-400">
                                {section.notes.map((note) => (
                                    <p key={note}>{note}</p>
                                ))}
                            </div>
                            <Link
                                href={section.link.href}
                                className="mt-5 inline-flex rounded-lg text-sm font-semibold text-indigo-600 hover:underline focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-indigo-400"
                            >
                                {section.link.label} →
                            </Link>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

Guide.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default Guide;
