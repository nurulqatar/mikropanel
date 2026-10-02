import {
    portalCountries,
    portalExtraText,
    portalLanguages,
} from '@/Hotel/portalV15Data';
import {
    useMemo,
    useState,
} from 'react';

export function localeCode(value) {
    const code =
        String(
            value || 'en',
        )
            .trim()
            .toLowerCase();

    if (
        code === 'zh-cn'
        || code === 'zh_cn'
    ) {
        return 'zh';
    }

    return code;
}

export function portalLanguage(
    locale,
) {
    const code =
        localeCode(
            locale,
        );

    return (
        portalLanguages.find(
            (item) =>
                item.code
                === code,
        )
        || portalLanguages[0]
    );
}

export function tx(
    locale,
    english,
) {
    const lang =
        portalLanguage(
            locale,
        );

    const en =
        portalLanguages.find(
            (item) =>
                item.code === 'en',
        );

    return (
        lang?.strings?.[
            english
        ]
        || en?.strings?.[
            english
        ]
        || english
    );
}

export function termsText(
    locale,
) {
    return (
        portalExtraText[
            localeCode(
                locale,
            )
        ]
        || portalExtraText.en
    );
}

export function portalDir(
    locale,
) {
    return portalLanguage(
        locale,
    ).rtl
        ? 'rtl'
        : 'ltr';
}

export function flagUrl(
    code,
) {
    return `/hotel-portal-preview/flags/${String(
        code || 'xx',
    ).toLowerCase()}.svg`;
}

export function PortalFrame({
    hotel,
    locale,
    children,
}) {
    const background =
        hotel.background_url
            ? {
                  backgroundImage:
                      `linear-gradient(rgba(3,11,25,.82),rgba(3,11,25,.93)),url("${hotel.background_url}")`,
              }
            : {};

    return (
        <div
            dir={portalDir(
                locale,
            )}
            style={
                background
            }
            className="min-h-screen bg-[radial-gradient(circle_at_top,_#153a70,_#07111f_52%,_#020617)] bg-cover bg-center px-4 py-7 text-slate-900"
        >
            <div className="mx-auto w-full max-w-lg">
                {children}
            </div>
        </div>
    );
}

export function BrandHeader({
    hotel,
    title,
    subtitle,
}) {
    return (
        <div className="rounded-t-[2rem] bg-gradient-to-br from-blue-600 via-indigo-600 to-cyan-500 px-6 py-7 text-center text-white">
            {hotel.logo_url ? (
                <img
                    src={
                        hotel.logo_url
                    }
                    alt={
                        hotel.name
                    }
                    className="mx-auto h-20 max-w-52 object-contain"
                />
            ) : (
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 text-lg font-black shadow-inner">
                    WiFi
                </div>
            )}

            <div className="mt-4 text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                {
                    hotel.name
                }
            </div>

            <h1 className="mt-2 text-2xl font-black">
                {title}
            </h1>

            {subtitle && (
                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-blue-100">
                    {
                        subtitle
                    }
                </p>
            )}
        </div>
    );
}

export function LanguagePicker({
    locale,
    onSelect,
}) {
    const [
        open,
        setOpen,
    ] = useState(
        false,
    );

    const [
        search,
        setSearch,
    ] = useState(
        '',
    );

    const current =
        portalLanguage(
            locale,
        );

    const rows =
        useMemo(
            () => {
                const q =
                    search
                        .trim()
                        .toLowerCase();

                if (!q) {
                    return portalLanguages;
                }

                return portalLanguages.filter(
                    (item) =>
                        item.name
                            .toLowerCase()
                            .includes(
                                q,
                            )
                        || item.native
                            .toLowerCase()
                            .includes(
                                q,
                            ),
                );
            },
            [search],
        );

    return (
        <>
            <button
                type="button"
                onClick={() =>
                    setOpen(
                        true,
                    )
                }
                className="flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-left shadow-sm"
            >
                <span>
                    <span className="block text-xs font-black uppercase tracking-wider text-slate-400">
                        🌐{' '}
                        {tx(
                            locale,
                            'Select Language',
                        )}
                    </span>

                    <span className="mt-1 block font-black text-slate-800">
                        {
                            current.native
                        }
                        <span className="ms-2 text-sm font-semibold text-slate-400">
                            {
                                current.name
                            }
                        </span>
                    </span>
                </span>

                <span className="text-xl text-slate-400">
                    ›
                </span>
            </button>

            {open && (
                <div
                    className="fixed inset-0 z-50 flex items-end bg-slate-950/65 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4"
                    onClick={() =>
                        setOpen(
                            false,
                        )
                    }
                >
                    <div
                        className="max-h-[82vh] w-full overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:max-w-lg sm:rounded-[2rem]"
                        onClick={(e) =>
                            e.stopPropagation()
                        }
                    >
                        <div className="border-b border-slate-100 p-5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h2 className="text-xl font-black">
                                        {tx(
                                            locale,
                                            'Select Language',
                                        )}
                                    </h2>

                                    <p className="mt-1 text-sm text-slate-400">
                                        {tx(
                                            locale,
                                            'Popular languages first',
                                        )}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    onClick={() =>
                                        setOpen(
                                            false,
                                        )
                                    }
                                    className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl"
                                >
                                    ×
                                </button>
                            </div>

                            <input
                                value={
                                    search
                                }
                                onChange={(e) =>
                                    setSearch(
                                        e.target.value,
                                    )
                                }
                                placeholder={tx(
                                    locale,
                                    'Search any language...',
                                )}
                                className="mt-4 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3"
                            />
                        </div>

                        <div className="max-h-[58vh] overflow-y-auto p-3">
                            {rows.map(
                                (
                                    item,
                                ) => (
                                    <button
                                        key={
                                            item.code
                                        }
                                        type="button"
                                        onClick={() => {
                                            onSelect(
                                                item.code,
                                            );

                                            setOpen(
                                                false,
                                            );

                                            setSearch(
                                                '',
                                            );
                                        }}
                                        className="flex w-full items-center justify-between rounded-2xl px-4 py-3 text-left hover:bg-slate-50"
                                    >
                                        <span>
                                            <span className="block text-base font-black">
                                                {
                                                    item.native
                                                }
                                            </span>

                                            <span className="block text-xs font-semibold text-slate-400">
                                                {
                                                    item.name
                                                }
                                            </span>
                                        </span>

                                        {item.code
                                            === localeCode(
                                                locale,
                                            ) && (
                                            <span className="font-black text-emerald-600">
                                                ✓
                                            </span>
                                        )}
                                    </button>
                                ),
                            )}

                            {rows.length
                                === 0 && (
                                <div className="p-8 text-center text-slate-400">
                                    {tx(
                                        locale,
                                        'No language found',
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

export function CountryPicker({
    locale,
    value,
    onSelect,
    dialMode = false,
}) {
    const [
        open,
        setOpen,
    ] = useState(
        false,
    );

    const [
        search,
        setSearch,
    ] = useState(
        '',
    );

    const current =
        portalCountries.find(
            (item) =>
                item.code
                === value,
        );

    const rows =
        useMemo(
            () => {
                const q =
                    search
                        .trim()
                        .toLowerCase();

                if (!q) {
                    return portalCountries;
                }

                return portalCountries.filter(
                    (item) =>
                        item.name
                            .toLowerCase()
                            .includes(
                                q,
                            )
                        || item.code
                            .toLowerCase()
                            .includes(
                                q,
                            )
                        || item.dial
                            .toLowerCase()
                            .includes(
                                q,
                            ),
                );
            },
            [search],
        );

    return (
        <>
            <button
                type="button"
                onClick={() =>
                    setOpen(
                        true,
                    )
                }
                className="flex min-h-14 w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left"
            >
                {current ? (
                    <span className="flex min-w-0 items-center gap-3">
                        <img
                            src={flagUrl(
                                current.code,
                            )}
                            alt=""
                            className="h-6 w-8 shrink-0 rounded object-cover shadow-sm"
                        />

                        <span className="min-w-0">
                            <span className="block truncate font-bold">
                                {
                                    current.name
                                }
                            </span>

                            {dialMode
                                && current.dial && (
                                <span className="block text-xs font-black text-blue-600">
                                    {
                                        current.dial
                                    }
                                </span>
                            )}
                        </span>
                    </span>
                ) : (
                    <span className="text-slate-400">
                        {dialMode
                            ? tx(
                                  locale,
                                  'Mobile Country Prefix',
                              )
                            : tx(
                                  locale,
                                  'Country',
                              )}
                    </span>
                )}

                <span className="ms-3 text-xl text-slate-400">
                    ›
                </span>
            </button>

            {open && (
                <div
                    className="fixed inset-0 z-50 flex items-end bg-slate-950/65 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4"
                    onClick={() =>
                        setOpen(
                            false,
                        )
                    }
                >
                    <div
                        className="max-h-[82vh] w-full overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:max-w-lg sm:rounded-[2rem]"
                        onClick={(e) =>
                            e.stopPropagation()
                        }
                    >
                        <div className="border-b border-slate-100 p-5">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-xl font-black">
                                    {dialMode
                                        ? tx(
                                              locale,
                                              'Mobile Country Prefix',
                                          )
                                        : tx(
                                              locale,
                                              'Country',
                                          )}
                                </h2>

                                <button
                                    type="button"
                                    onClick={() =>
                                        setOpen(
                                            false,
                                        )
                                    }
                                    className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl"
                                >
                                    ×
                                </button>
                            </div>

                            <input
                                autoFocus
                                value={
                                    search
                                }
                                onChange={(e) =>
                                    setSearch(
                                        e.target.value,
                                    )
                                }
                                placeholder={dialMode
                                    ? tx(
                                          locale,
                                          '🔎 Search country / prefix...',
                                      )
                                    : tx(
                                          locale,
                                          '🔎 Search country...',
                                      )}
                                className="mt-4 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3"
                            />
                        </div>

                        <div className="max-h-[58vh] overflow-y-auto p-3">
                            {rows.map(
                                (
                                    item,
                                ) => (
                                    <button
                                        key={
                                            item.code
                                        }
                                        type="button"
                                        onClick={() => {
                                            onSelect(
                                                item,
                                            );

                                            setOpen(
                                                false,
                                            );

                                            setSearch(
                                                '',
                                            );
                                        }}
                                        className="flex w-full items-center justify-between rounded-2xl px-4 py-3 text-left hover:bg-slate-50"
                                    >
                                        <span className="flex min-w-0 items-center gap-3">
                                            <img
                                                src={flagUrl(
                                                    item.code,
                                                )}
                                                alt=""
                                                className="h-6 w-8 shrink-0 rounded object-cover shadow-sm"
                                            />

                                            <span className="min-w-0">
                                                <span className="block truncate font-bold">
                                                    {
                                                        item.name
                                                    }
                                                </span>

                                                <span className="block text-xs font-semibold text-slate-400">
                                                    {
                                                        item.code
                                                    }
                                                </span>
                                            </span>
                                        </span>

                                        {dialMode
                                            && item.dial && (
                                            <span className="font-black text-blue-600">
                                                {
                                                    item.dial
                                                }
                                            </span>
                                        )}
                                    </button>
                                ),
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

export function TextField({
    label,
    value,
    onChange,
    type = 'text',
    placeholder = '',
    min,
    autoComplete,
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-black text-slate-700">
                {
                    label
                }
            </span>

            <input
                type={
                    type
                }
                value={
                    value
                }
                min={
                    min
                }
                autoComplete={
                    autoComplete
                }
                placeholder={
                    placeholder
                }
                onChange={(e) =>
                    onChange(
                        e.target.value,
                    )
                }
                className="w-full rounded-2xl border-slate-200 bg-white px-4 py-3.5 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
        </label>
    );
}
