import { StepComponent } from './step-component';
import { RouterModule } from './../router/router.module';
import { UserService } from './../../services/user.service';
import { ToastModule } from './../toast/toast.module';
import { BasketService } from './../../services/basket.service';
import { BasketModel } from './models/basket.model';
import { StringToNumberHelper } from '../../helpers/string-to-number.helper';
import { DialogModule } from './../dialog/dialog.module';
/**
 * @name BasketListModule
 * @desc Affichage de la liste des produits dans le panier
 * @author IDea Factory - Déc. 2018 (dev-team@ideafactory.fr)
 * @package modules\basket
 * @version 1.0.0
 */
export class BasketListModule {
    private basket: Array<BasketModel>;
    private userService: UserService;
    private stepComponent: StepComponent;

    public constructor() {
        console.log('BasketListModule works !');
        this.userService = new UserService();

        this.userService.hasUser().then((has) => {
            this._init().then((panier) => {
                this.basket = panier;
    
                // Instancie le gestionnaire de progression
                this.stepComponent = new StepComponent(this.userService, this.basket);
                
                // Construit le panier
                const tbody: JQuery = $('#basket-list tbody');
                let granTotal: number = 0;
                let fullTaxTotal: number = 0;
    
                if (this.basket.length) {
                    for (let product of this.basket) {
                        let total: number = product.priceHT * product.quantity;
                        granTotal += total;
    
                        fullTaxTotal += this._getTTC(product, product.product);
    
                        product.getTableRow().then((row) => {
                            tbody.append(row);
                            // Ajouter le total HT au pied de tableau
                            $('.gran-total').html(StringToNumberHelper.toCurrency(granTotal.toString()));
                            $('.fulltax-total').html(StringToNumberHelper.toCurrency(fullTaxTotal.toString()));
                        });
                    }
                    $('#basket-list').removeClass('hidden');
                    
                    // Initialise les listeners
                    this._listeners();
                } else {
                    // Le panier est vide...
    
                }
            });

            // Gestion des alertes
            const warning: JQuery = $('#basket-warns');
            let disableButton: boolean = false;
            if (!has) {
                // Pas encore d'utilisateur connecté
                const noUser: JQuery = warning.children('.no-user').eq(0);
                noUser.removeClass('inactive');
                disableButton = true;
            } else {
                if (!this.userService.getUser().hasAddresses()) {
                    const noAddress: JQuery = warning.children('.no-address').eq(0);
                    noAddress.removeClass('inactive');
                    disableButton = true;
                }
            }
            if (disableButton) {
                $('#next-step').attr('disabled', 'disabled');
            }
        });

    }

    /**
     * Récupère les produits du panier
     */
    private _init(): Promise<Array<BasketModel>> {
        return new Promise((resolve) => {
            const basketService: BasketService = new BasketService();
            basketService.localBasket().then((panier) => {
                resolve(panier);
            });
        });
    }

    private _listeners(): void {
        $('#basket-list tbody').on(
            'click',
            (event: any): void => this._click(event)
        );
    }

    private _click(event: any): void {
        const element: JQuery = $(event.target);
        const bundleId: string = element.parents('tr').attr('id');

        if (element.hasClass('remove-product')) {
            console.info('Suppression d\'un produit de la liste : ' + bundleId);
            this._remove(element);
        } else if (element.hasClass('increase')) {
            console.info('Augmenter la quantité du produit ' + bundleId);
            this._increase(element);
        } else if (element.hasClass('decrease')) {
            console.info('Diminuer la quantité de produit ' + bundleId);
            this._decrease(element);
        }
    }

    private _remove(element: JQuery): void {
        const productName: string = element.parents('tr').eq(0).children('td').eq(0).html();
        const dialog: DialogModule = new DialogModule(
            element,
            {
                title: 'Retirer un produit du panier',
                content: 'Etes-vous sûr de vouloir retirer<br><strong>' + productName + '</strong><br>de votre panier ?'
            }
        );
        dialog.show();

        element.off('dismiss').on(
            'dismiss',
            (event: any): void => {
                console.info('Dialogue dismissed');
                event.stopPropagation();
            }
        );

        element.off('confirm').on(
            'confirm',
            (event: any) => {
                console.info('Suppression de la ligne ' + $(event.target).attr('data-rel'));
                const basketService: BasketService = new BasketService();
                
                basketService.removeProduct($(event.target).attr('data-rel')).then((result) => {
                    if (result) {
                        // Suppression de la ligne dans le tableau HTML
                        $('tr#' + $(event.target).attr('data-rel')).remove();
                        const product: BasketModel = basketService.get();
                        // Recalcule les totaux...
                        let totalHT: number = StringToNumberHelper.toNumber($('.gran-total').html());
                        let totalTTC: number = StringToNumberHelper.toNumber($('.fulltax-total').html());

                        const removeHT: number = product.quantity * product.priceHT;
                        totalHT -= removeHT;

                        if (product.product.vat === 0.05) {
                            product.product.vat = 0.055;
                        }
                        const removeTTC: number = (product.quantity * product.priceHT) * (1 + product.product.vat);
                        totalTTC -= removeTTC;

                        // Réaffiche les totaux
                        $('.gran-total').html(StringToNumberHelper.toCurrency(totalHT.toString()));
                        $('.fulltax-total').html(StringToNumberHelper.toCurrency(totalTTC.toString()));

                        // Si plus aucune ligne dans le panier, on réactive le hidden
                        $('#basket-list').addClass('hidden');

                        // on ajoute un message...
                        const toast: ToastModule = new ToastModule({
                            type: 'info',
                            title: 'Votre panier est vide',
                            position: 'middle-center',
                            message: 'Plus aucun produit dans votre panier.'
                        });
                        toast.show();

                        // on redirige vers l'accueil
                        const router: RouterModule = new RouterModule();
                        router.changeLocation('/');
                    } else {
                        const toast: ToastModule = new ToastModule({
                            type: 'danger',
                            title: 'Une erreur est survenue',
                            position: 'middle-center',
                            message: 'Une erreur est survenue lors du retrait du produit de votre panier'
                        });
                        toast.show();
                    }
                });
            }
        )
    }

    private _increase(button: JQuery): void {
        const input: JQuery = button.parent().prev();
        let currentQty: number = parseInt(input.val().toString());
        const max: number = parseInt(input.attr('max'));

        let totalHT: number = StringToNumberHelper.toNumber($('.gran-total').html());
        let totalTTC: number = StringToNumberHelper.toNumber($('.fulltax-total').html());

        console.log('Totaux courants : ' + totalHT + ' (ttc) ' + totalTTC);

        if (currentQty + 1 <= max) {
            currentQty++;
            input.val(currentQty);

            // Met à jour le panier en conséquence
            const trId: string = input.parents('tr').attr('id');
            const basketService: BasketService = new BasketService();
            basketService.updateProduct(trId, currentQty).then((product) => {
                if (product) {
                    // Recalcul des totaux
                    let newHT: number = currentQty * product.priceHT;
 
                    if (product.product.vat === 0.05) {
                        product.product.vat = 0.055;
                    }
                    const ttc: number = StringToNumberHelper.toNumber(input.parents('tr').children('td').eq(3).html());
                    let newTTC: number = currentQty * ttc;

                    //let newTTC: number = parseFloat(((currentQty * product.priceHT) * (1 + product.product.vat)).toFixed(2));
                    let totalIncrement: number = parseFloat(((product.priceHT) * (1 + product.product.vat)).toFixed(2));

                    // Mise à jour de la colonne associée
                    const _col: JQuery = input.parents('tr').children('td').eq(4);
                    _col.html(StringToNumberHelper.toCurrency(newTTC.toString()));



                    console.log('Avant mise à jour total HT : ' + totalHT);

                    totalHT += newHT;
                    totalTTC += totalIncrement;

                    $('.gran-total').html(StringToNumberHelper.toCurrency(totalHT.toString()));
                    $('.fulltax-total').html(StringToNumberHelper.toCurrency(totalTTC.toString()));
                }
            });
        }
    }

    private _decrease(button: JQuery): void {
        const input: JQuery = button.parent().next();
        let currentQty: number = parseInt(input.val().toString());
        const min: number = parseInt(input.attr('min'));

        let totalHT: number = StringToNumberHelper.toNumber($('.gran-total').html());
        let totalTTC: number = StringToNumberHelper.toNumber($('.fulltax-total').html());

        console.log('Totaux courants : ' + totalHT + ' (ttc) ' + totalTTC);

        if (currentQty - 1 >= min) {
            currentQty--;
            input.val(currentQty);

            // Met à jour le panier en conséquence
            const trId: string = input.parents('tr').attr('id');
            const basketService: BasketService = new BasketService();
            basketService.updateProduct(trId, currentQty).then((product) => {
                if (product) {
                    // Recalcul des totaux
                    let newHT: number = currentQty * product.priceHT;
 
                    if (product.product.vat === 0.05) {
                        product.product.vat = 0.055;
                    }
                    const ttc: number = StringToNumberHelper.toNumber(input.parents('tr').children('td').eq(3).html());
                    let newTTC: number = currentQty * ttc;
                    
                    //let newTTC: number = parseFloat(((currentQty * product.priceHT) * (1 + product.product.vat)).toFixed(2));
                    let totalIncrement: number = parseFloat(((product.priceHT) * (1 + product.product.vat)).toFixed(2));

                    // Mise à jour de la colonne associée
                    const _col: JQuery = input.parents('tr').children('td').eq(4);
                    _col.html(StringToNumberHelper.toCurrency(newTTC.toString()));



                    console.log('Avant mise à jour total HT : ' + totalHT);

                    totalHT -= newHT;
                    totalTTC -= totalIncrement;

                    $('.gran-total').html(StringToNumberHelper.toCurrency(totalHT.toString()));
                    $('.fulltax-total').html(StringToNumberHelper.toCurrency(totalTTC.toString()));
                }
            });
        }
    }

    private _getTTC(inBasket: BasketModel, product: any): number {
        const priceList: Array<any> = product.pricing;
        
        let price: any;

        if (priceList.length > 1) {
            const index: number = priceList.findIndex((obj) => { return obj.quantity == inBasket.servingSize});
            price = priceList[index];
        } else {
            price = priceList[0];
        }

        let vat: number = product.vat;
        if (vat === 0.05) {
            vat = 0.055;
        }
        return inBasket.quantity * (inBasket.priceHT * (1 + vat));
    }
}