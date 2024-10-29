import ReactDOM from "react-dom";
import { useEffect, useState } from "@wordpress/element";
import PopStringModal from "./popupStringModal";
import yandexLanguage from "./component/TranslateProvider/yandex/yandex-language";
const { sprintf, __ } = wp.i18n;

const PopupModal = (props) => {
    const [fetchStatus, setFetchStatus] = useState(false);
    const [targetBtn, setTargetBtn] = useState({});
    const [blockRules, setBlockRules] = useState({});
    const [modalRender, setModalRender] = useState({});
    const [settingVisibility, setSettingVisibility] = useState(false);
    const sourceLang = atfp_ajax_object.source_lang;
    const targetLang = props.targetLang;
    const sourceLangName = atfp_ajax_object.languageObject[sourceLang];
    const targetLangName = atfp_ajax_object.languageObject[targetLang];
    const apiUrl = atfp_ajax_object.ajax_url;
    const imgFolder = atfp_ajax_object.atfp_url + 'assets/images/';
    const yandexSupport = yandexLanguage().includes(targetLang);

    /**
     * Prepare data to send in API request.
     */
    const apiSendData = {
        atfp_nonce: atfp_ajax_object.ajax_nonce,
        action: atfp_ajax_object.action_block_rules
    };

    /**
     * Update the fetch status state.
     * @param {boolean} state - The state to update the fetch status with.
     */
    const updateFetch = (state) => {
        setFetchStatus(state);
    }

    const openModalOnLoadHandler=(e)=>{
        e.preventDefault();
        const btnElement=e.target;
        const visibility=btnElement.dataset.value;

        if(visibility === 'yes'){
            setSettingVisibility(true);
        }

        btnElement.closest('#atfp-modal-open-warning-wrapper').remove();
    }

    /**
     * useEffect hook to set settingVisibility.
     * Triggers the setSettingVisibility only when user click on meta field Button.
    */
    useEffect(() => {
        const firstRenderBtns=document.querySelectorAll('#atfp-modal-open-warning-wrapper .modal-content button');
        const metaFieldBtn = document.querySelector('input#atfp-translate-button[name="atfp_meta_box_translate"]');

        if (metaFieldBtn) {
            metaFieldBtn.addEventListener('click', () => {
                setSettingVisibility(prev => !prev);
            });
        }

        firstRenderBtns.forEach(ele=>{
            if(ele){
                ele.addEventListener('click',openModalOnLoadHandler);
            }
        })
    }, [])

    /**
     * useEffect hook to fetch block rules data from the server.
     * Triggers the fetch only when fetchStatus is true and blockRules is empty.
     */
    useEffect(() => {
        if (Object.keys(blockRules).length > 0 || !fetchStatus) {
            return;
        }

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json',
            },
            body: new URLSearchParams(apiSendData)
        })
            .then(response => response.json())
            .then(data => {
                const blockRules = JSON.parse(data.data.blockRules);

                setBlockRules(blockRules);
            })
            .catch(error => {
                console.error('Error fetching post content:', error);
            });
    }, [fetchStatus])

    /**
     * useEffect hook to handle displaying the modal and rendering the PopStringModal component.
     * Renders the modal only when blockRules is not empty and fetchStatus is true.
     */
    useEffect(() => {

        if (Object.keys(blockRules).length <= 0) {
            return;
        }

        const btn = targetBtn;
        const service = btn.dataset && btn.dataset.service;
        const serviceLabel = btn.dataset && btn.dataset.serviceLabel;
        const postId = props.postId;

        const parentWrp = document.getElementById("atfp_strings_model");

        if (fetchStatus && Object.keys(blockRules).length > 0) {
            ReactDOM.render(<PopStringModal
                blockRules={blockRules}
                visibility={fetchStatus}
                updateFetch={updateFetch}
                postId={postId}
                service={service}
                serviceLabel={serviceLabel}
                sourceLang={sourceLang}
                targetLang={targetLang}
                modalRender={modalRender}
                pageTranslate={props.pageTranslate}
            />, parentWrp);
            setSettingVisibility(prev => !prev);
        }
    }, [fetchStatus, blockRules]);

    /**
     * Function to handle fetching content based on the target button clicked.
     * Sets the target button and updates the fetch status to true.
     * @param {Event} e - The event object representing the button click.
     */
    const fetchContent = (e) => {
        let targetElement = !e.target.classList.contains('atfp-service-btn') ? e.target.closest('.atfp-service-btn') : e.target;
        setModalRender(prev => prev + 1);
        setTargetBtn(targetElement);
        setFetchStatus(true);
    };

    return (
        <>
            {settingVisibility &&
                <div className="modal-container" style={{ display: settingVisibility ? 'flex' : 'none' }}>
                    <div className="atfp-settings modal-content">
                        <div className="modal-header">
                            <h2>{__("Step 1 - Select Translation Provider (Beta)", 'automatic-translations-for-polylang')}</h2>
                            <h4>{sprintf(__("Translate %(postType)s content from %(source)s to %(target)s", 'automatic-translations-for-polylang'), { postType: props.postType, source: sourceLangName, target: targetLangName })}</h4>
                            <p class="atfp-error-message" style={{marginBottom: '.5rem'}}>{sprintf(__("This translation widget replaces the current %(postType)s content with the original %(source)s %(postType)s and translates it into %(target)s", 'automatic-translations-for-polylang'),{ postType: props.postType, source: sourceLangName, target: targetLangName})}</p>
                            <span className="close" onClick={() => setSettingVisibility(false)}>&times;</span>
                        </div>
                        <hr />
                        <strong className="atlt-heading">{__("Translate Using Yandex Page Translate Widget", 'automatic-translations-for-polylang')}</strong>
                        <div className="inputGroup">
                            {yandexSupport ?
                                <>
                                    <button className="atfp-service-btn translate button button-primary" data-service="yandex" data-service-label="Yandex Translate" onClick={fetchContent}>{__("Yandex Translate (Beta)", 'automatic-translations-for-polylang')}</button>
                                    <br />
                                </>
                                :
                                <>
                                    <button className="atfp-service-btn translate button button-primary" disabled={true}>{__("Yandex Translate (Beta)", 'automatic-translations-for-polylang')}</button><br />
                                    <span className="atfp-error-message">{targetLangName} {__('language is not supported by Yandex Translate', 'automatic-translations-for-polylang')}.</span>
                                </>
                            }
                            <a href="https://translate.yandex.com/" target="_blank"><img className="pro-features-img" src={`${imgFolder}powered-by-yandex.png`} alt="powered by Yandex Translate Widget" /></a>
                        </div>
                        <hr />
                        <ul style={{ margin: "0" }}>
                            <li><span style={{ color: "green" }}>✔</span> {__("Unlimited Translations with Yandex Translate", 'automatic-translations-for-polylang')}</li>
                            <li><span style={{ color: "green" }}>✔</span> {__("No API Key Required for Yandex Translate", 'automatic-translations-for-polylang')}</li>
                            <li><span style={{ color: "green" }}>✔</span> {__("Supports Multiple Languages", 'automatic-translations-for-polylang')} - <a href="https://yandex.com/support2/translate-desktop/en/supported-langs" target="_blank">{__("See Supported Languages", 'automatic-translations-for-polylang')}</a></li>
                        </ul>
                        <hr />
                        <div className="modal-footer">
                            <button className="atfp-setting-close" onClick={() => setSettingVisibility(false)}>{__("Close", 'automatic-translations-for-polylang')}</button>
                        </div>
                    </div>
                </div>
            }
        </>
    );
};

export default PopupModal;