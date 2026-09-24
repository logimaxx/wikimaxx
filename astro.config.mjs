// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
	site: 'https://academy.maxxops.ro',
	integrations: [
		starlight({
			title: 'MaxxOps Academy',
			description:
				'Documentație MaxxOps: ghiduri pentru utilizatori + materiale pentru echipă (sales, onboarding, colaboratori).',
			defaultLocale: 'root',
			locales: {
				root: {
					label: 'Română',
					lang: 'ro',
				},
			},
			social: [
				{
					icon: 'external',
					label: 'maxxops.ro',
					href: 'https://maxxops.ro',
				},
			],
			customCss: ['./src/styles/custom.css'],
			sidebar: [
				{
					label: 'Pentru utilizatori',
					items: [
						{
							label: 'Pornire',
							items: [
								{ label: 'Ce este MaxxOps', slug: 'pornire/ce-este-maxxops' },
								{ label: 'Primul login', slug: 'pornire/primul-login' },
								{ label: 'Unde găsesc ce', slug: 'pornire/unde-gasesc' },
							],
						},
						{
							label: 'Pe rol',
							items: [
								{ label: 'Vânzări / back-office', slug: 'pe-rol/vanzari' },
								{ label: 'Depozit / operațiuni', slug: 'pe-rol/depozit' },
								{ label: 'Tehnician teren', slug: 'pe-rol/tehnician-teren' },
								{ label: 'Administrator firmă', slug: 'pe-rol/admin' },
								{ label: 'Pontaj (angajat)', slug: 'pe-rol/pontaj' },
								{ label: 'Kiosk magazin', slug: 'pe-rol/kiosk' },
							],
						},
						{
							label: 'Fluxuri',
							items: [
								{ label: 'Comandă → expediere', slug: 'fluxuri/comanda-la-expediere' },
								{ label: 'Factură', slug: 'fluxuri/factura' },
								{ label: 'AWB / livrare', slug: 'fluxuri/awb' },
								{ label: 'Stoc insuficient', slug: 'fluxuri/stoc-insuficient' },
							],
						},
						{
							label: 'Integrări',
							items: [
								{ label: 'Facturare (SmartBill)', slug: 'integrari/smartbill' },
								{ label: 'Curier (GLS)', slug: 'integrari/gls' },
								{ label: 'Canale (Woo / eMAG)', slug: 'integrari/canale' },
							],
						},
						{
							label: 'Video',
							items: [{ label: 'Tutoriale video', slug: 'video' }],
						},
					],
				},
				{
					label: 'Echipă (intern)',
					badge: { text: 'Intern', variant: 'caution' },
					items: [
						{ label: 'Hub echipă', slug: 'echipa' },
						{
							label: 'Onboarding',
							items: [
								{ label: 'Bun venit', slug: 'echipa/onboarding/bun-venit' },
								{ label: 'Prima săptămână', slug: 'echipa/onboarding/prima-saptamana' },
								{ label: 'Colaboratori externi', slug: 'echipa/onboarding/colaboratori' },
							],
						},
						{
							label: 'Sales',
							items: [
								{ label: 'Produsul pe scurt', slug: 'echipa/sales/produsul' },
								{ label: 'Pentru cine vindem', slug: 'echipa/sales/pentru-cine' },
								{ label: 'Obiecții frecvente', slug: 'echipa/sales/obiectii' },
								{ label: 'Strategii & proces', slug: 'echipa/sales/strategii' },
							],
						},
					],
				},
			],
		}),
	],
});
