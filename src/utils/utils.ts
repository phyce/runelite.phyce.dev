import {RequestEvent, RequestEventLoader} from "@builder.io/qwik-city";

export function formatDate(dateString: string, userLocale: string = 'en-GB'): string {
	const options: Intl.DateTimeFormatOptions = {
		year: 'numeric',
		month: 'long',
		day: 'numeric',
	};
	return new Intl.DateTimeFormat(userLocale, options).format(new Date(dateString));
}

export function formatDateTime(dateString: string, userLocale: string = 'en-GB'): string {
	const options: Intl.DateTimeFormatOptions = {
		year: 'numeric',
		month: 'long',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	};
	return new Intl.DateTimeFormat(userLocale, options).format(new Date(dateString));
}

export function truncateString(str: string, num: number, suffix: string = '…'): string {
	if (str.length <= num) {
		return str;
	}
	return str.slice(0, num) + suffix;
}

export function formatNumber(num: number): string {
	return num.toLocaleString('en-US');
}

export function getRecordCookie(requestEvent: RequestEventLoader, name:string = 'getParams'): Record<string, string> {
	const paramCookie = requestEvent.cookie.get(name);
	let parameters: Record<string, string> = {};
	if (paramCookie) parameters = JSON.parse(paramCookie.value);

	return parameters;
}

export function updateUrlParamsFromCookie() {
	const cookieHeader = document.cookie || '';
	const cookies = new Map(cookieHeader.split('; ').map(c => {
		const [key, val] = c.split('=');
		return [key, decodeURIComponent(val)];
	}));
	const savedParams = cookies.get('getParams') ? JSON.parse(cookies.get('getParams') as string) : {};

	const url = new URL(window.location.href);

	Object.entries(savedParams).forEach(([key, value]) => {
		url.searchParams.set(key, value as string);
	});

	window.history.replaceState({}, '', url.toString());
}