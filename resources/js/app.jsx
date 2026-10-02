import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

/*
 * COMPANY_TERMINOLOGY_GLOBAL_UI_V4B
 *
 * Backend compatibility keeps the technical
 * reseller/reseller_id terminology internally.
 *
 * User-facing terminology:
 * Reseller  => Company
 * Resellers => Companies
 */
const normalizeCompanyTerminology = (value) => {
    if (
        typeof value !== 'string'
        || value.length === 0
    ) {
        return value;
    }

    return value
        .replace(
            /\bRESELLERS\b/g,
            'COMPANIES',
        )
        .replace(
            /\bRESELLER\b/g,
            'COMPANY',
        )
        .replace(
            /\bResellers\b/g,
            'Companies',
        )
        .replace(
            /\bReseller\b/g,
            'Company',
        )
        .replace(
            /\bresellers\b/g,
            'companies',
        )
        .replace(
            /\breseller\b/g,
            'company',
        );
};

const installCompanyTerminologyGuard = () => {
    if (
        typeof document === 'undefined'
        || !document.documentElement
    ) {
        return;
    }

    const ignoredTags = new Set([
        'SCRIPT',
        'STYLE',
        'CODE',
        'PRE',
        'TEXTAREA',
    ]);

    const normalizeTextNode = (node) => {
        if (
            !node
            || node.nodeType
                !== Node.TEXT_NODE
        ) {
            return;
        }

        const parent =
            node.parentElement;

        if (
            !parent
            || ignoredTags.has(
                parent.tagName,
            )
        ) {
            return;
        }

        const before =
            node.nodeValue;

        const after =
            normalizeCompanyTerminology(
                before,
            );

        if (after !== before) {
            node.nodeValue = after;
        }
    };

    const normalizeElement = (element) => {
        if (
            !element
            || element.nodeType
                !== Node.ELEMENT_NODE
        ) {
            return;
        }

        for (
            const attribute
            of [
                'placeholder',
                'title',
                'aria-label',
                'alt',
            ]
        ) {
            if (
                !element.hasAttribute(
                    attribute,
                )
            ) {
                continue;
            }

            const before =
                element.getAttribute(
                    attribute,
                );

            const after =
                normalizeCompanyTerminology(
                    before,
                );

            if (after !== before) {
                element.setAttribute(
                    attribute,
                    after,
                );
            }
        }

        if (
            element.tagName === 'INPUT'
            && [
                'button',
                'submit',
                'reset',
            ].includes(
                String(
                    element.type || ''
                ).toLowerCase(),
            )
        ) {
            const before =
                element.value;

            const after =
                normalizeCompanyTerminology(
                    before,
                );

            if (after !== before) {
                element.value = after;
            }
        }
    };

    const normalizeTree = (root) => {
        if (!root) {
            return;
        }

        if (
            root.nodeType
                === Node.TEXT_NODE
        ) {
            normalizeTextNode(root);
            return;
        }

        if (
            root.nodeType
                !== Node.ELEMENT_NODE
            && root.nodeType
                !== Node.DOCUMENT_NODE
            && root.nodeType
                !== Node.DOCUMENT_FRAGMENT_NODE
        ) {
            return;
        }

        if (
            root.nodeType
                === Node.ELEMENT_NODE
        ) {
            normalizeElement(root);
        }

        const walker =
            document.createTreeWalker(
                root,
                NodeFilter.SHOW_ELEMENT
                    | NodeFilter.SHOW_TEXT,
            );

        let node =
            walker.nextNode();

        while (node) {
            if (
                node.nodeType
                    === Node.TEXT_NODE
            ) {
                normalizeTextNode(node);
            } else {
                normalizeElement(node);
            }

            node =
                walker.nextNode();
        }
    };

    const normalizeTitle = () => {
        const before =
            document.title;

        const after =
            normalizeCompanyTerminology(
                before,
            );

        if (after !== before) {
            document.title = after;
        }
    };

    normalizeTree(
        document.documentElement,
    );

    normalizeTitle();

    const observer =
        new MutationObserver(
            (mutations) => {
                for (
                    const mutation
                    of mutations
                ) {
                    if (
                        mutation.type
                            === 'characterData'
                    ) {
                        normalizeTextNode(
                            mutation.target,
                        );

                        continue;
                    }

                    if (
                        mutation.type
                            === 'attributes'
                    ) {
                        normalizeElement(
                            mutation.target,
                        );

                        continue;
                    }

                    for (
                        const node
                        of mutation.addedNodes
                    ) {
                        normalizeTree(node);
                    }
                }

                normalizeTitle();
            },
        );

    observer.observe(
        document.documentElement,
        {
            subtree: true,
            childList: true,
            characterData: true,
            attributes: true,
            attributeFilter: [
                'placeholder',
                'title',
                'aria-label',
                'alt',
                'value',
            ],
        },
    );
};

if (
    typeof document !== 'undefined'
) {
    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            installCompanyTerminologyGuard,
            {
                once: true,
            },
        );
    } else {
        installCompanyTerminologyGuard();
    }
}
