import {
    directionForLocale,
    languageOptions,
} from '@/Hotel/portalCatalog';
import {
    createTranslator,
} from '@/Hotel/portalI18n';
import {
    Head,
    router,
} from '@inertiajs/react';
import {
    useMemo,
    useState,
} from 'react';

export default function Welcome({
    hotel,
    locale,
    languages = [],
}) {
    const [
        selected,
        setSelected,
    ] = useState(locale);

    const t =
        createTranslator(
            selected,
            hotel.portal_translations,
        );

    const options =
        useMemo(
            () =>
                languageOptions(
                    selected,
                    languages,
                ),
            [
                selected,
                languages,
            ],
        );

    const background =
        hotel.background_url
            ? {
                  backgroundImage:
                      `linear-gradient(rgba(2,6,23,.58),rgba(2,6,23,.88)),url("${hotel.background_url}")`,
              }
            : {
                  background:
                      `linear-gradient(145deg, ${hotel.secondary_color}, #020617)`,
              };

    return (
        <div
            dir={directionForLocale(
                selected,
            )}
            className="flex min-h-screen items-center justify-center bg-cover bg-center p-4 md:p-6"
            style={background}
        >
            <Head
                title={
                    hotel.portal_title
                }
            />

            <div className="w-full max-w-xl overflow-hidden rounded-[2rem] border border-white/20 bg-white/95 shadow-2xl backdrop-blur-xl">
                <div
                    className="h-2"
                    style={{
                        backgroundColor:
                            hotel.primary_color,
                    }}
                />

                <div className="p-7 md:p-10">
                    <div className="text-center">
                        {hotel.logo_url ? (
                            <img
                                src={
                                    hotel.logo_url
                                }
                                alt={
                                    hotel.name
                                }
                                className="mx-auto mb-5 h-24 max-w-56 object-contain"
                            />
                        ) : (
                            <div
                                className="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-3xl text-2xl font-black text-white shadow-lg"
                                style={{
                                    backgroundColor:
                                        hotel.primary_color,
                                }}
                            >
                                WiFi
                            </div>
                        )}

                        <h1 className="text-3xl font-black text-slate-900">
                            {
                                hotel.portal_title
                            }
                        </h1>

                        {hotel.portal_subtitle && (
                            <p className="mt-3 text-slate-500">
                                {
                                    hotel.portal_subtitle
                                }
                            </p>
                        )}
                    </div>

                    <div className="mt-8">
                        <label className="text-sm font-black text-slate-700">
                            {t(
                                'selectLanguage',
                            )}
                        </label>

                        <select
                            value={selected}
                            onChange={(e) =>
                                setSelected(
                                    e.target.value,
                                )
                            }
                            className="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-base"
                        >
                            {options.map(
                                (
                                    language,
                                ) => (
                                    <option
                                        key={
                                            language.code
                                        }
                                        value={
                                            language.code
                                        }
                                    >
                                        {
                                            language.name
                                        }
                                    </option>
                                ),
                            )}
                        </select>
                    </div>

                    <button
                        type="button"
                        onClick={() =>
                            router.visit(
                                route(
                                    'hotel.portal.access',
                                    {
                                        hotel:
                                            hotel.slug,

                                        lang:
                                            selected,
                                    },
                                ),
                            )
                        }
                        style={{
                            backgroundColor:
                                hotel.primary_color,
                        }}
                        className="mt-6 w-full rounded-xl px-6 py-3.5 text-lg font-black text-white shadow-lg"
                    >
                        {t('go')}
                    </button>

                    <div className="mt-6 text-center text-xs text-slate-400">
                        {hotel.name}
                    </div>
                </div>
            </div>
        </div>
    );
}
