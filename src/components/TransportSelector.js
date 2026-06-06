/**
 * TransportSelector — exclusive radio cards for transport selection.
 * default | smtp
 */
import { __ } from '@wordpress/i18n';

const OPTIONS = [
	{
		value: 'default',
		label: __( 'Disabled', 'camaleaunmail' ),
		description: __( "Uses PHP's default mail() function. Not recommended for production — emails often land in spam.", 'camaleaunmail' ),
	},
	{
		value: 'smtp',
		label: __( 'SMTP', 'camaleaunmail' ),
		description: __( 'Send through any SMTP server — your host, Mailgun, SendGrid, etc.', 'camaleaunmail' ),
	},
];

export default function TransportSelector( { value, onChange } ) {
	return (
		<section className="cam-section">
			<h2 className="cam-section__title">
				{ __( 'Mail transport', 'camaleaunmail' ) }
			</h2>
			<p className="cam-section__description">
				{ __( 'Choose how WordPress sends email.', 'camaleaunmail' ) }
			</p>
			<div className="cam-transport-cards" role="radiogroup">
				{ OPTIONS.map( opt => (
					<label
						key={ opt.value }
						className={ `cam-transport-card${ value === opt.value ? ' is-selected' : '' }` }
					>
						<input
							type="radio"
							name="cam-transport"
							value={ opt.value }
							checked={ value === opt.value }
							onChange={ () => onChange( opt.value ) }
							className="cam-transport-card__radio"
						/>
						<span className="cam-transport-card__body">
							<span className="cam-transport-card__label">{ opt.label }</span>
							<span className="cam-transport-card__desc">{ opt.description }</span>
						</span>
					</label>
				) ) }
			</div>
		</section>
	);
}
