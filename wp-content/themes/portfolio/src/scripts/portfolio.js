function portfolioContact() {
	return {
		busy: false,
		message: '',
		failed: false,
		async submit(event) {
			if (this.busy) return;
			const form = event.target;
			if (!form.reportValidity()) return;
			this.busy = true;
			this.message = '';
			this.failed = false;
			try {
				const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
				const result = await response.json();
				if (!result.success) throw new Error(result.data?.message || 'ارسال پیام انجام نشد. دوباره تلاش کنید.');
				this.message = result.data.message;
				form.reset();
			} catch (error) {
				this.failed = true;
				this.message = error.message || 'ارتباط برقرار نشد. دوباره تلاش کنید.';
			} finally {
				this.busy = false;
			}
		}
	};
}
