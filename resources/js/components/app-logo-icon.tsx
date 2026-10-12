import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
            <circle
                cx="32"
                cy="32"
                r="20"
                fill="none"
                stroke="currentColor"
                strokeWidth="3"
            />
            <path
                d="M32 16v16l12-7"
                fill="none"
                stroke="#128F86"
                strokeWidth="3.2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="32" cy="32" r="3.2" fill="#E0A106" />
        </svg>
    );
}
