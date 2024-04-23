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