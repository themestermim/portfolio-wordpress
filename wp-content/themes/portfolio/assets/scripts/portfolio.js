function portfolioContact() {
	return {
		busy: false,
		message: '',
		failed: false,
		requestId: '',
		init() {
			this.newRequestId();
		},
		newRequestId() {
			this.requestId = globalThis.crypto?.randomUUID
				? globalThis.crypto.randomUUID()
				: 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
					const random = Math.floor(Math.random() * 16);
					return (character === 'x' ? random : (random & 3) | 8).toString(16);
				});
		},
		async submit(event) {
			if (this.busy) return;
			const form = event.target;
			if (!form.reportValidity()) return;
			this.busy = true;
			this.message = '';
			this.failed = false;
			try {
				const body = new FormData(form);
				body.set('request_id', this.requestId);
				const response = await fetch(form.getAttribute('action'), { method: 'POST', body, credentials: 'same-origin' });
				const result = await response.json();
				if (!result.success) throw new Error(result.data?.message || 'ارسال پیام انجام نشد. دوباره تلاش کنید.');
				this.message = result.data.message;
				form.reset();
				this.newRequestId();
			} catch (error) {
				this.failed = true;
				this.message = error.message || 'ارتباط برقرار نشد. دوباره تلاش کنید.';
			} finally {
				this.busy = false;
			}
		}
	};
}

function portfolioProjects() {
	return {
		expanded: false,
		showAll() {
			this.expanded = true;
		}
	};
}
