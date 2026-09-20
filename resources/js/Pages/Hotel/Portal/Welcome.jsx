import {
    Head,
    router,
} from '@inertiajs/react';
import { useState } from 'react';

export default function Welcome({
    hotel,
    locale,
    languages = [],
}) {
    const [
        selected,
        setSelected,
    ] = useState(locale);

    const style =
        hotel.background_url
            ? {
                  backgroundImage:
                      `linear-gradient(rgba(2,6,23,.70),rgba(2,6,23,.82)),url("${hotel.background_url}")`,
              }
            : {};

    return (
        <div
            className="flex min-h-screen items-center justify-center bg-slate-950 bg-cover bg-center p-5"
            style={style}
        >
            <Head
                title={
                    hotel.portal_title
                }
            />

            <div className="w-full max-w-xl rounded-[2rem] border border-white/10 bg-white/95 p-8 text-center shadow-2xl backdrop-blur">
                {hotel.logo_url && (
                    <img
                        src={
                            hotel.logo_url
                        }
                        alt={
                            hotel.name
                        }
                        className="mx-auto mb-5 h-24 max-w-56 object-contain"
                    />
                )}

                <h1 className="text-3xl font-black text-slate-900">
                    {hotel.portal_title}
                </h1>

                {hotel.portal_subtitle && (
                    <p className="mt-3 text-slate-500">
                        {
                            hotel.portal_subtitle
                        }
                    </p>
                )}

                <div className="mt-8 text-left">
                    <label className="text-sm font-black text-slate-700">
                        Select Language
                    </label>

                    <select
                        value={selected}
                        onChange={(e) =>
                            setSelected(
                                e.target.value,
                            )
                        }
                        className="mt-2 w-full rounded-xl border-slate-300"
                    >
                        {languages.map(
                            (language) => (
                                <option
                                    key={
                                        language
                                    }
                                    value={
                                        language
                                    }
                                >
                                    {language.toUpperCase()}
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
                    className="mt-6 w-full rounded-xl px-6 py-3 font-black text-white"
                >
                    Go
                </button>
            </div>
        </div>
    );
}
